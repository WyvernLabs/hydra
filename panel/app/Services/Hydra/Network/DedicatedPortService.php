<?php

namespace Pterodactyl\Services\Hydra\Network;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\ProxyAddon;

/**
 * Hydra: the owner of a server with a dedicated node picks the ports of the
 * server. A port is added on the address of the primary allocation; one that
 * is used by another server or by the node itself is refused.
 */
class DedicatedPortService
{
    public const MIN_PORT = 1024;

    public function __construct(private ProxyAddon $proxyAddon)
    {
    }

    /**
     * @throws DisplayException
     */
    public function add(Server $server, int $port): Allocation
    {
        if (!$server->hydra_dedicated) {
            throw new DisplayException(__('server/network.not_dedicated'));
        }
        if ($port < self::MIN_PORT || $port > 65535) {
            throw new DisplayException(__('server/network.port_range', ['min' => self::MIN_PORT]));
        }
        if ($server->allocation_limit > 0 && $server->allocations()->count() >= $server->allocation_limit) {
            throw new DisplayException(__('server/network.port_limit'));
        }

        $node = $server->node;
        if (in_array($port, $this->reserved($server), true)) {
            throw new DisplayException(__('server/network.port_reserved', ['port' => $port]));
        }

        $primary = $server->allocation;
        /** @var Allocation|null $existing */
        $existing = Allocation::query()
            ->where('node_id', $node->id)
            ->where('ip', $primary->ip)
            ->where('port', $port)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ($existing->server_id === $server->id) {
                throw new DisplayException(__('server/network.port_yours', ['port' => $port]));
            }
            if (!is_null($existing->server_id)) {
                throw new DisplayException(__('server/network.port_taken', ['port' => $port]));
            }

            $existing->forceFill(['server_id' => $server->id])->save();

            return $existing;
        }

        /** @var Allocation $allocation */
        $allocation = Allocation::query()->create([
            'node_id' => $node->id,
            'ip' => $primary->ip,
            'ip_alias' => $primary->ip_alias,
            'port' => $port,
            'server_id' => $server->id,
            'hydra_dedicated' => true,
        ]);

        return $allocation;
    }

    /**
     * Ports the node uses itself.
     *
     * @return int[]
     */
    public function reserved(Server $server): array
    {
        $node = $server->node;
        $ports = [22, (int) $node->daemonListen, (int) $node->daemonSFTP];
        if ($this->proxyAddon->enabled()) {
            $ports = array_merge($ports, [80, 443]);
        }

        return array_values(array_unique(array_filter($ports)));
    }
}
