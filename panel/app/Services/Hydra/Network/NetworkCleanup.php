<?php

namespace Pterodactyl\Services\Hydra\Network;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\HydraProxy;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\HydraSubdomain;

/**
 * Hydra: removes what belongs to a port or server when it goes away: the
 * subdomains (DNS records on Cloudflare), the reverse proxies on the node,
 * and the ports a dedicated server added itself.
 */
class NetworkCleanup
{
    public function __construct(private SubdomainService $subdomains, private ProxyService $proxies)
    {
    }

    /**
     * Before a port leaves its server: its proxies and subdomains go with it.
     *
     * @throws \Throwable
     */
    public function forAllocation(Allocation $allocation): void
    {
        $proxies = HydraProxy::query()->where('allocation_id', $allocation->id)->get();
        foreach ($proxies as $proxy) {
            $this->proxies->delete($proxy, false);
        }
        if ($proxies->isNotEmpty() && $allocation->node) {
            $this->proxies->sync($allocation->node);
        }

        HydraSubdomain::query()->where('allocation_id', $allocation->id)->get()
            ->each(fn (HydraSubdomain $subdomain) => $this->subdomains->delete($subdomain));
    }

    /**
     * Unassigns a port from its server, removing it when the server added it
     * itself on its dedicated node.
     *
     * @throws \Throwable
     */
    public function release(Allocation $allocation): void
    {
        $this->forAllocation($allocation);

        if ($allocation->hydra_dedicated) {
            $allocation->delete();

            return;
        }

        Allocation::query()->where('id', $allocation->id)->update(['notes' => null, 'server_id' => null]);
    }

    /**
     * Before a server is deleted. With $force, problems are logged and the
     * server is deleted anyway.
     *
     * @throws \Throwable
     */
    public function forServer(Server $server, bool $force = false): void
    {
        $attempt = function (callable $callback) use ($force) {
            try {
                $callback();
            } catch (\Throwable $exception) {
                if (!$force) {
                    throw $exception;
                }
                Log::warning($exception);
            }
        };

        $proxies = $server->proxies()->get();
        foreach ($proxies as $proxy) {
            $attempt(fn () => $this->proxies->delete($proxy, false));
        }
        if ($proxies->isNotEmpty()) {
            $attempt(fn () => $this->proxies->sync($server->node));
        }

        foreach ($server->subdomains()->get() as $subdomain) {
            $attempt(fn () => $this->subdomains->delete($subdomain));
        }

        // Ports added on a dedicated node belong to nobody else.
        $server->allocations()->where('hydra_dedicated', true)->where('id', '!=', $server->allocation_id)->delete();
    }
}
