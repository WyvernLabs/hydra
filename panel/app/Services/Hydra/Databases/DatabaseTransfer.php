<?php

namespace Pterodactyl\Services\Hydra\Databases;

use Pterodactyl\Models\Database;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\Service\Hydra\DatabaseBrowserException;

/**
 * Hydra: import and export of server databases as SQL files, with the client
 * tools of MariaDB on the panel server (installed by the panel installer).
 * Both run with the credentials of the database itself.
 */
class DatabaseTransfer
{
    private const CHUNK = 1024 * 1024;

    public function __construct(private Encrypter $encrypter, private DatabaseBrowser $browser)
    {
    }

    /**
     * Writes a gzip-compressed dump of the database to the output.
     *
     * @throws DatabaseBrowserException when the dump cannot be started
     */
    public function export(Database $database): \Closure
    {
        $binary = $this->binary(['mariadb-dump', 'mysqldump']);

        // Fails early with a readable message, before the download starts.
        $this->browser->for($database)->pdo();

        return function () use ($database, $binary) {
            $this->withOptionsFile($database, function (string $options) use ($database, $binary) {
                $process = proc_open([
                    $binary,
                    '--defaults-extra-file=' . $options,
                    '--single-transaction',
                    '--routines',
                    '--triggers',
                    '--no-tablespaces',
                    '--skip-lock-tables',
                    '--default-character-set=utf8mb4',
                    $database->database,
                ], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

                if (!is_resource($process)) {
                    return;
                }

                $gzip = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
                while (!feof($pipes[1])) {
                    $chunk = fread($pipes[1], self::CHUNK);
                    if ($chunk === false) {
                        break;
                    }
                    if ($chunk !== '') {
                        echo deflate_add($gzip, $chunk, ZLIB_NO_FLUSH);
                        flush();
                    }
                }
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);

                $tail = '';
                if (proc_close($process) !== 0) {
                    // The download has started already, so the error goes into the file.
                    $tail = "\n-- Hydra: the export failed: " . str_replace("\n", ' ', trim((string) $errors)) . "\n";
                }
                echo deflate_add($gzip, $tail, ZLIB_FINISH);
                flush();
            });
        };
    }

    /**
     * Runs an SQL file (optionally gzip-compressed) against the database.
     *
     * @throws DatabaseBrowserException
     */
    public function import(Database $database, string $path): void
    {
        $binary = $this->binary(['mariadb', 'mysql']);
        $this->browser->for($database)->pdo();

        $source = @fopen(($this->isGzip($path) ? 'compress.zlib://' : '') . $path, 'rb');
        if ($source === false) {
            throw DatabaseBrowserException::tool(__('server/databases.errors.import_read'));
        }

        $this->withOptionsFile($database, function (string $options) use ($database, $binary, $source) {
            $process = proc_open([
                $binary,
                '--defaults-extra-file=' . $options,
                '--default-character-set=utf8mb4',
                '--database=' . $database->database,
            ], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (!is_resource($process)) {
                throw DatabaseBrowserException::tool(__('server/databases.errors.tool_missing'));
            }

            while (($chunk = fread($source, self::CHUNK)) !== false && $chunk !== '') {
                // The client stops at the first error and closes its input.
                if (@fwrite($pipes[0], $chunk) === false) {
                    break;
                }
            }
            fclose($pipes[0]);
            $errors = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[2]);

            if (proc_close($process) !== 0) {
                throw DatabaseBrowserException::tool(__('server/databases.errors.import_failed', ['message' => mb_substr($errors, 0, 2000)]));
            }
        });

        @fclose($source);
    }

    /**
     * The password goes into a temporary options file instead of the command
     * line, where other users of the server could see it.
     */
    private function withOptionsFile(Database $database, \Closure $callback): void
    {
        $file = tempnam(sys_get_temp_dir(), 'hydra-db-');
        chmod($file, 0600);

        $escape = fn (string $value) => '"' . addcslashes($value, "\\\"") . '"';
        file_put_contents($file, implode("\n", [
            '[client]',
            'host=' . $escape($database->host->host),
            'port=' . (int) $database->host->port,
            'user=' . $escape($database->username),
            'password=' . $escape($this->encrypter->decrypt($database->password)),
            '',
        ]));

        try {
            $callback($file);
        } finally {
            @unlink($file);
        }
    }

    private function binary(array $names): string
    {
        foreach ($names as $name) {
            foreach (['/usr/bin/', '/usr/local/bin/', '/bin/'] as $dir) {
                if (is_executable($dir . $name)) {
                    return $dir . $name;
                }
            }
        }

        throw DatabaseBrowserException::tool(__('server/databases.errors.tool_missing'));
    }

    private function isGzip(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $magic = fread($handle, 2);
        fclose($handle);

        return $magic === "\x1f\x8b";
    }
}
