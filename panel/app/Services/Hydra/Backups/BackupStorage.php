<?php

namespace Pterodactyl\Services\Hydra\Backups;

use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hydra: backups are limited by the space they use instead of their number.
 * Backups on the user's own SFTP server do not count.
 */
class BackupStorage
{
    /**
     * Backup space of the server in bytes, 0 when it has none.
     */
    public static function limit(Server $server): int
    {
        return max(0, (int) $server->backup_storage_limit) * 1024 * 1024;
    }

    /**
     * MiB from a value in GB entered in the admin area (decimals allowed).
     */
    public static function mbFromGb(mixed $gb): int
    {
        return is_numeric($gb) ? (int) round(max(0, (float) $gb) * 1024) : 0;
    }

    /**
     * Bytes used by the finished backups that count against the space.
     */
    public static function used(Server $server): int
    {
        return (int) self::counted($server)->where('is_successful', true)->sum('bytes');
    }

    /**
     * Backups that count against the space of the server: all but those on
     * the user's own SFTP server.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Backup, Server>
     */
    public static function counted(Server $server)
    {
        return $server->backups()->where(function (Builder $query) {
            $query->where('disk', '!=', Backup::ADAPTER_SFTP)
                ->orWhereHas('sftpTarget', fn (Builder $target) => $target->whereNotNull('node_id'));
        });
    }
}
