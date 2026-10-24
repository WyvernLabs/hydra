<?php

namespace Pterodactyl\Services\Hydra\Motd;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Jobs\Hydra\SyncMotdJob;
use Pterodactyl\Services\Hydra\Addons\MotdAddon;

/**
 * Hydra: builds the MOTD configuration of a node (addon "motd") and sends it
 * to Wings, which keeps it and answers for the servers listed in it.
 */
class MotdService
{
    public function __construct(private MotdAddon $addon)
    {
    }

    /**
     * What Wings gets: texts, picture and the Minecraft servers of the node.
     */
    public function payload(Node $node): array
    {
        $enabled = $this->addon->enabled() && !empty($this->addon->eggIds());
        $servers = $enabled
            ? Server::query()->where('node_id', $node->id)->whereIn('egg_id', $this->addon->eggIds())->get(['uuid', 'name'])
            : collect();

        $panel = parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) config('app.url');
        $states = array_map(
            fn (array $texts) => array_map(fn (string $text) => str_replace('{panel}', $panel, $text), $texts),
            $this->addon->states()
        );

        return [
            'enabled' => $enabled,
            'favicon' => $enabled ? ($this->addon->favicon() ?? '') : '',
            'states' => $states,
            'servers' => $servers->pluck('uuid')->values()->all(),
            'names' => (object) $servers->pluck('name', 'uuid')->all(),
        ];
    }

    /**
     * Sends the configuration to every node in the background.
     */
    public function syncAll(): void
    {
        Node::query()->get()->each(fn (Node $node) => $this->sync($node));
    }

    /**
     * Sends the configuration to the node in the background.
     */
    public function sync(Node $node): void
    {
        SyncMotdJob::dispatch($node->id, $this->payload($node));
    }

    /**
     * Whether a change of the server matters for the MOTD of its node.
     */
    public function concerns(Server $server): bool
    {
        if (!$this->addon->enabled()) {
            return false;
        }
        $eggs = $this->addon->eggIds();

        return in_array($server->egg_id, $eggs, true) || in_array((int) $server->getOriginal('egg_id'), $eggs, true);
    }
}
