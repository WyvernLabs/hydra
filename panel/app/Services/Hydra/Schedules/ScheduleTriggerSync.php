<?php

namespace Pterodactyl\Services\Hydra\Schedules;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: the node watches for the states and console lines that start
 * schedules. They are part of the server's configuration, so the node is
 * told to fetch it again whenever a schedule changes.
 */
class ScheduleTriggerSync
{
    public function __construct(private DaemonServerRepository $daemon)
    {
    }

    public function sync(Server $server): void
    {
        try {
            $this->daemon->setServer($server)->sync();
        } catch (\Throwable $exception) {
            // The node fetches the configuration again when it starts.
            Log::warning('Could not sync the schedule triggers of a server with its node.', [
                'server' => $server->uuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
