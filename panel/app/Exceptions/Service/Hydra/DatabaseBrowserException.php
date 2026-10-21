<?php

namespace Pterodactyl\Exceptions\Service\Hydra;

use Illuminate\Http\Response;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: errors of the database manager in the client area. Messages of the
 * database server are passed on, they only concern the user's own database.
 */
class DatabaseBrowserException extends DisplayException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public static function connection(\Throwable $previous): self
    {
        return new self(__('server/databases.errors.connection', ['message' => self::clean($previous)]), $previous);
    }

    public static function query(\Throwable $previous): self
    {
        return new self(self::clean($previous), $previous);
    }

    public static function tableMissing(string $table): self
    {
        return new self(__('server/databases.errors.table_missing', ['table' => $table]));
    }

    public static function unknownColumn(string $column): self
    {
        return new self(__('server/databases.errors.unknown_column', ['column' => $column]));
    }

    public static function noPrimaryKey(): self
    {
        return new self(__('server/databases.errors.no_primary_key'));
    }

    public static function rowMissing(): self
    {
        return new self(__('server/databases.errors.row_missing'));
    }

    public static function tool(string $message): self
    {
        return new self($message);
    }

    /**
     * "SQLSTATE[42S02]: Base table or view not found: 1146 Table ..." → the
     * part after the code, which is what users know from their clients.
     */
    private static function clean(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        return trim(preg_replace('/^SQLSTATE\[[0-9A-Z]+\](?: \[\d+\])?:?\s*/', '', $message) ?? $message);
    }
}
