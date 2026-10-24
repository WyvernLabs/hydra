<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Backups\DeleteBackupService;
use Pterodactyl\Services\Hydra\Network\NetworkCleanup;

/**
 * Hydra: everything a server set up outside of its node is removed before it
 * is deleted: subdomains, reverse proxies, ports added on a dedicated node and
 * backups (the node keeps local backups otherwise, and those on S3 or the
 * node's SFTP server would stay there). Backups on the user's own SFTP server
 * are the user's and stay.
 */
class ServerCleanup
{
    public function __construct(private NetworkCleanup $network, private DeleteBackupService $backups)
    {
    }

    /**
     * Runs while the server still exists on the node. With $force, problems
     * are logged and the deletion goes on.
     *
     * @throws \Throwable
     */
    public function beforeDeletion(Server $server, bool $force = false): void
    {
        $this->network->forServer($server, $force);

        foreach ($server->backups()->get() as $backup) {
            if ($backup->location() === 'own_sftp') {
                continue;
            }

            try {
                // A locked backup is deleted with its server as well.
                $backup->is_locked = false;
                $this->backups->handle($backup);
            } catch (\Throwable $exception) {
                if (!$force) {
                    throw $exception;
                }
                Log::warning($exception);
            }
        }
    }

    /**
     * Runs once the server is gone: its primary port is removed too when the
     * server had added it itself on its dedicated node.
     */
    public function afterDeletion(?int $primaryAllocation): void
    {
        if ($primaryAllocation) {
            Allocation::query()->where('id', $primaryAllocation)->where('hydra_dedicated', true)->whereNull('server_id')->delete();
        }
    }
}
