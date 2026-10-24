<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\HydraProxy;

/**
 * Hydra: users point a domain to a port of their server with HTTPS, e.g. for
 * a website or a live map like BlueMap. Caddy on the node serves it; the node
 * installer sets it up and opens the ports 80 and 443.
 */
class ProxyAddon extends Addon
{
    public function key(): string
    {
        return 'proxy';
    }

    public function icon(): string
    {
        return 'waypoints';
    }

    public function rules(): array
    {
        return [
            'default_limit' => 'required|integer|min:0|max:100',
        ];
    }

    public function summary(): array
    {
        return [
            trans('admin/addons.proxy.summary', [
                'count' => HydraProxy::query()->count(),
                'limit' => $this->defaultLimit(),
            ]),
        ];
    }

    public function defaultLimit(): int
    {
        return (int) $this->get('default_limit', 1);
    }

    /**
     * Proxies the server may have.
     */
    public function limit(Server $server): int
    {
        return $server->hydra_proxy_limit ?? $this->defaultLimit();
    }
}
