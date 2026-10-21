<?php

namespace Pterodactyl\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Pterodactyl\Services\Hydra\NodeDatabaseService;

/**
 * Hydra: opens the database port of the database hosts set up on nodes for
 * the current list of nodes, after a node was added, changed or removed.
 */
class SyncDatabaseFirewallJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 60;

    public function handle(NodeDatabaseService $service): void
    {
        // Errors are logged per node; the admin can sync again on the
        // database tab of the node.
        $service->syncFirewall();
    }
}
