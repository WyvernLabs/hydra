<?php

namespace Pterodactyl\Jobs\Hydra;

use Pterodactyl\Models\Node;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Repositories\Wings\DaemonMotdRepository;

/**
 * Hydra: sends the MOTD configuration (addon "motd") to a node. The payload
 * is built when the job is dispatched, since the long running queue worker
 * does not see settings changed after it started.
 */
class SyncMotdJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $node, public array $payload)
    {
    }

    public function handle(DaemonMotdRepository $daemon): void
    {
        $node = Node::query()->find($this->node);
        if ($node) {
            $daemon->setNode($node)->put($this->payload);
        }
    }
}
