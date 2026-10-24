<?php

namespace Pterodactyl\Observers;

use Pterodactyl\Events;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Node;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Pterodactyl\Services\Hydra\Motd\MotdService;

class ServerObserver
{
    use DispatchesJobs;

    /**
     * Listen to the Server creating event.
     */
    public function creating(Server $server): void
    {
        event(new Events\Server\Creating($server));
    }

    /**
     * Listen to the Server created event.
     */
    public function created(Server $server): void
    {
        event(new Events\Server\Created($server));

        $this->syncMotd($server);
    }

    /**
     * Listen to the Server deleting event.
     */
    public function deleting(Server $server): void
    {
        event(new Events\Server\Deleting($server));
    }

    /**
     * Listen to the Server deleted event.
     */
    public function deleted(Server $server): void
    {
        event(new Events\Server\Deleted($server));

        $this->syncMotd($server);
    }

    /**
     * Listen to the Server saving event.
     */
    public function saving(Server $server): void
    {
        event(new Events\Server\Saving($server));
    }

    /**
     * Listen to the Server saved event.
     */
    public function saved(Server $server): void
    {
        event(new Events\Server\Saved($server));
    }

    /**
     * Listen to the Server updating event.
     */
    public function updating(Server $server): void
    {
        event(new Events\Server\Updating($server));
    }

    /**
     * Listen to the Server saved event.
     */
    public function updated(Server $server): void
    {
        event(new Events\Server\Updated($server));

        if ($server->wasChanged(['egg_id', 'name', 'node_id', 'allocation_id'])) {
            $this->syncMotd($server);
        }
    }

    /**
     * Hydra: the MOTD of the node lists its Minecraft servers (addon "motd").
     */
    private function syncMotd(Server $server): void
    {
        $service = app(MotdService::class);
        if (!$service->concerns($server)) {
            return;
        }

        $nodes = array_unique(array_filter([$server->node_id, $server->wasChanged('node_id') ? (int) $server->getOriginal('node_id') : null]));
        foreach (Node::query()->whereIn('id', $nodes)->get() as $node) {
            $service->sync($node);
        }
    }
}
