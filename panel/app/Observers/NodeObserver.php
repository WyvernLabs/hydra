<?php

namespace Pterodactyl\Observers;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\DatabaseHost;
use Pterodactyl\Jobs\SyncDatabaseFirewallJob;

/**
 * Hydra: database hosts set up on a node only accept connections from the
 * nodes, so their firewall follows the list of nodes.
 */
class NodeObserver
{
    public function created(Node $node): void
    {
        $this->sync();
    }

    public function updated(Node $node): void
    {
        if ($node->wasChanged('fqdn')) {
            $this->sync();
        }
    }

    public function deleted(Node $node): void
    {
        $this->sync();
    }

    private function sync(): void
    {
        if (DatabaseHost::query()->where('hydra_managed', true)->exists()) {
            SyncDatabaseFirewallJob::dispatch()->afterCommit();
        }
    }
}
