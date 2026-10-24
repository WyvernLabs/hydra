<?php

namespace Pterodactyl\Services\Hydra\Databases;

use PDO;
use PDOException;
use Pterodactyl\Models\Database;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\Service\Hydra\DatabaseBrowserException;

/**
 * Hydra: shows and edits the tables of a server database in the client area.
 *
 * Every query runs with the credentials of the database itself, so users can
 * never reach more than they could with their own database client. Table and
 * column names are only ever taken from the schema of the database, values
 * are always bound as parameters.
 */
class DatabaseBrowser
{
    public const PAGE_SIZE = 50;

    /** Rows returned for a query of the SQL console. */
    public const QUERY_LIMIT = 1000;

    /** Longer values are cut in the table view and loaded in full when a row is edited. */
    public const PREVIEW_LENGTH = 500;

    private const BINARY_TYPES = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection'];

    private ?PDO $pdo = null;

    private Database $database;

    public function __construct(private Encrypter $encrypter)
    {
    }

    public function for(Database $database): self
    {
        $browser = clone $this;
        $browser->database = $database;
        $browser->pdo = null;

        return $browser;
    }

    /**
     * @throws DatabaseBrowserException
     */
    public function pdo(): PDO
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        $host = $this->database->host;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host->host, $host->port, $this->database->database);

        try {
            $this->pdo = new PDO($dsn, $this->database->username, $this->encrypter->decrypt($this->database->password), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ]);
        } catch (PDOException $exception) {
            throw DatabaseBrowserException::connection($exception);
        }

        return $this->pdo;
    }

    /**
     * Tables and views of the database.
     */
    public function tables(): array
    {
        $statement = $this->run(
            'SELECT TABLE_NAME AS name, TABLE_TYPE AS type, ENGINE AS engine, TABLE_ROWS AS `rows`,
                    COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS size, TABLE_COLLATION AS collation
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
            [$this->database->database]
        );

        return array_map(fn (array $row) => [
            'name' => $row['name'],
            'view' => $row['type'] === 'VIEW',
            'engine' => $row['engine'],
            'rows' => is_null($row['rows']) ? null : (int) $row['rows'],
            'size' => (int) $row['size'],
            'collation' => $row['collation'],
        ], $statement->fetchAll());
    }

    /**
     * Number of tables and size of the whole database in bytes.
     *
     * @return array{tables: int, size: int}
     */
    public function stats(): array
    {
        $row = $this->run(
            'SELECT COUNT(*) AS tables, COALESCE(SUM(COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0)), 0) AS size
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$this->database->database]
        )->fetch();

        return ['tables' => (int) ($row['tables'] ?? 0), 'size' => (int) ($row['size'] ?? 0)];
    }

    /**
     * Columns of a table, in their order.
     */
    public function columns(string $table): array
    {
        $this->assertTable($table);

        $statement = $this->run(
            'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, DATA_TYPE AS data_type, IS_NULLABLE AS nullable,
                    COLUMN_DEFAULT AS `default`, COLUMN_KEY AS `key`, EXTRA AS extra, COLUMN_COMMENT AS comment
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$this->database->database, $table]
        );

        return array_map(fn (array $row) => [
            'name' => $row['name'],
            'type' => $row['type'],
            'nullable' => $row['nullable'] === 'YES',
            'default' => $row['default'],
            'primary' => $row['key'] === 'PRI',
            'key' => $row['key'],
            'extra' => $row['extra'],
            'comment' => $row['comment'],
            'binary' => in_array(strtolower($row['data_type']), self::BINARY_TYPES, true),
        ], $statement->fetchAll());
    }

    public function indexes(string $table): array
    {
        $this->assertTable($table);

        $indexes = [];
        foreach ($this->run('SHOW INDEX FROM ' . $this->quote($table))->fetchAll() as $row) {
            $name = $row['Key_name'];
            $indexes[$name] ??= ['name' => $name, 'unique' => !$row['Non_unique'], 'type' => $row['Index_type'], 'columns' => []];
            $indexes[$name]['columns'][] = $row['Column_name'];
        }

        return array_values($indexes);
    }

    /**
     * One page of rows. Rows can only be changed in tables with a primary key.
     */
    public function rows(string $table, int $page, ?string $sort = null, string $direction = 'asc'): array
    {
        $columns = $this->columns($table);
        $names = array_column($columns, 'name');
        $primary = array_values(array_column(array_filter($columns, fn ($c) => $c['primary']), 'name'));

        $total = (int) $this->run('SELECT COUNT(*) FROM ' . $this->quote($table))->fetchColumn();
        $page = max(1, $page);

        $sql = 'SELECT * FROM ' . $this->quote($table);
        if (!is_null($sort) && in_array($sort, $names, true)) {
            $sql .= ' ORDER BY ' . $this->quote($sort) . (strtolower($direction) === 'desc' ? ' DESC' : ' ASC');
        } elseif (!empty($primary)) {
            $sql .= ' ORDER BY ' . implode(', ', array_map(fn ($c) => $this->quote($c), $primary));
        }
        $sql .= sprintf(' LIMIT %d OFFSET %d', self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        $rows = array_map(fn (array $row) => $this->present($row, $columns, true), $this->run($sql)->fetchAll());

        return [
            'columns' => $columns,
            'primary' => $primary,
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'total_pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    /**
     * A single row with its full values, for editing.
     */
    public function row(string $table, array $key): array
    {
        $columns = $this->columns($table);
        [$where, $bindings] = $this->where($columns, $key);

        $row = $this->run('SELECT * FROM ' . $this->quote($table) . " WHERE $where LIMIT 1", $bindings)->fetch();
        if (!$row) {
            throw DatabaseBrowserException::rowMissing();
        }

        return $this->present($row, $columns, false)['values'];
    }

    /**
     * @param array<string, string|null> $values
     */
    public function insert(string $table, array $values): void
    {
        $columns = $this->columns($table);
        $values = $this->writable($columns, $values);
        if (empty($values)) {
            $this->run('INSERT INTO ' . $this->quote($table) . ' () VALUES ()');

            return;
        }

        $this->run(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->quote($table),
                implode(', ', array_map(fn ($c) => $this->quote($c), array_keys($values))),
                implode(', ', array_fill(0, count($values), '?'))
            ),
            array_values($values)
        );
    }

    /**
     * @param array<string, string|null> $key
     * @param array<string, string|null> $values
     */
    public function update(string $table, array $key, array $values): void
    {
        $columns = $this->columns($table);
        [$where, $bindings] = $this->where($columns, $key);
        $values = $this->writable($columns, $values);
        if (empty($values)) {
            return;
        }

        $set = implode(', ', array_map(fn ($c) => $this->quote($c) . ' = ?', array_keys($values)));
        $statement = $this->run('UPDATE ' . $this->quote($table) . " SET $set WHERE $where LIMIT 1", array_merge(array_values($values), $bindings));

        if ($statement->rowCount() === 0 && !$this->exists($table, $columns, $key)) {
            throw DatabaseBrowserException::rowMissing();
        }
    }

    /**
     * @param array<string, string|null> $key
     */
    public function delete(string $table, array $key): void
    {
        $columns = $this->columns($table);
        [$where, $bindings] = $this->where($columns, $key);

        if ($this->run('DELETE FROM ' . $this->quote($table) . " WHERE $where LIMIT 1", $bindings)->rowCount() === 0) {
            throw DatabaseBrowserException::rowMissing();
        }
    }

    /**
     * Runs a single statement of the SQL console.
     */
    public function query(string $sql): array
    {
        $started = microtime(true);
        $statement = $this->run($sql);

        if ($statement->columnCount() === 0) {
            return ['columns' => [], 'rows' => [], 'affected' => $statement->rowCount(), 'truncated' => false, 'time' => round((microtime(true) - $started) * 1000)];
        }

        $columns = [];
        for ($i = 0; $i < $statement->columnCount(); ++$i) {
            $meta = $statement->getColumnMeta($i) ?: [];
            $columns[] = ['name' => $meta['name'] ?? (string) $i, 'binary' => false];
        }

        $rows = [];
        $truncated = false;
        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            if (count($rows) >= self::QUERY_LIMIT) {
                $truncated = true;
                break;
            }
            $rows[] = array_map(fn ($value) => $this->display($value, true), $row);
        }
        $statement->closeCursor();

        return ['columns' => $columns, 'rows' => $rows, 'affected' => null, 'truncated' => $truncated, 'time' => round((microtime(true) - $started) * 1000)];
    }

    /**
     * Values of a row for the client: binary values are not sent, long values
     * are cut in the table view.
     */
    private function present(array $row, array $columns, bool $preview): array
    {
        $values = [];
        $cut = [];
        foreach ($columns as $column) {
            $value = $row[$column['name']] ?? null;
            if (!is_null($value) && $column['binary']) {
                $values[$column['name']] = sprintf('[BINARY %d B]', strlen((string) $value));
                continue;
            }
            $display = $this->display($value, $preview);
            if ($preview && is_string($value) && is_string($display) && mb_strlen($display) < mb_strlen($value)) {
                $cut[] = $column['name'];
            }
            $values[$column['name']] = $display;
        }

        return ['values' => $values, 'truncated' => $cut];
    }

    private function display(mixed $value, bool $preview): mixed
    {
        if (is_null($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        $value = (string) $value;
        if (!mb_check_encoding($value, 'UTF-8')) {
            return sprintf('[BINARY %d B]', strlen($value));
        }

        return $preview && mb_strlen($value) > self::PREVIEW_LENGTH ? mb_substr($value, 0, self::PREVIEW_LENGTH) : $value;
    }

    /**
     * Only known, non-binary columns can be written.
     */
    private function writable(array $columns, array $values): array
    {
        $allowed = array_column(array_filter($columns, fn ($c) => !$c['binary']), 'name');

        $result = [];
        foreach ($values as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                throw DatabaseBrowserException::unknownColumn((string) $name);
            }
            $result[$name] = is_null($value) ? null : (string) $value;
        }

        return $result;
    }

    /**
     * WHERE clause for a row, by its full primary key.
     */
    private function where(array $columns, array $key): array
    {
        $primary = array_values(array_column(array_filter($columns, fn ($c) => $c['primary']), 'name'));
        if (empty($primary)) {
            throw DatabaseBrowserException::noPrimaryKey();
        }
        if (count($key) !== count($primary) || array_diff($primary, array_keys($key))) {
            throw DatabaseBrowserException::rowMissing();
        }

        $parts = [];
        $bindings = [];
        foreach ($primary as $name) {
            if (is_null($key[$name])) {
                $parts[] = $this->quote($name) . ' IS NULL';
                continue;
            }
            $parts[] = $this->quote($name) . ' = ?';
            $bindings[] = (string) $key[$name];
        }

        return [implode(' AND ', $parts), $bindings];
    }

    private function exists(string $table, array $columns, array $key): bool
    {
        [$where, $bindings] = $this->where($columns, $key);

        return (bool) $this->run('SELECT 1 FROM ' . $this->quote($table) . " WHERE $where LIMIT 1", $bindings)->fetchColumn();
    }

    private function assertTable(string $table): void
    {
        $exists = $this->run(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$this->database->database, $table]
        )->fetchColumn();

        if (!$exists) {
            throw DatabaseBrowserException::tableMissing($table);
        }
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function run(string $sql, array $bindings = []): \PDOStatement
    {
        try {
            if (empty($bindings)) {
                return $this->pdo()->query($sql);
            }
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($bindings);

            return $statement;
        } catch (PDOException $exception) {
            throw DatabaseBrowserException::query($exception);
        }
    }
}
