<?php

namespace Pterodactyl\Services\Hydra\Network;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\HydraProxy;
use Pterodactyl\Models\HydraDomain;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonProxyRepository;

/**
 * Hydra: reverse proxies of servers (addon "proxy"). Caddy on the node serves
 * the domain with HTTPS and passes the requests on to a port of the server.
 * The node always gets the full list of its proxies.
 */
class ProxyService
{
    public function __construct(private DaemonProxyRepository $daemon, private SubdomainService $subdomains)
    {
    }

    /**
     * Creates a proxy for the user's own domain, which has to point to the
     * node already, or for a new subdomain of a Cloudflare domain.
     *
     * @throws \Throwable
     */
    public function create(Server $server, Allocation $allocation, ?string $domain, ?HydraDomain $managed = null, ?string $name = null): HydraProxy
    {
        if ($allocation->server_id !== $server->id) {
            throw new DisplayException(__('server/network.allocation_invalid'));
        }

        $subdomain = null;
        if ($managed) {
            $subdomain = $this->subdomains->create($server, $managed, (string) $name, $allocation, null, true);
            $domain = $subdomain->fqdn();
        } else {
            $domain = $this->validDomain((string) $domain);
            $this->assertPointsToNode($domain, $server->node);
        }

        if (HydraProxy::query()->where('domain', $domain)->exists()) {
            $subdomain && $this->subdomains->delete($subdomain);

            throw new DisplayException(__('server/network.proxy_taken', ['domain' => $domain]));
        }

        /** @var HydraProxy $proxy */
        $proxy = HydraProxy::query()->create([
            'server_id' => $server->id,
            'domain' => $domain,
            'allocation_id' => $allocation->id,
            'subdomain_id' => $subdomain?->id,
        ]);

        try {
            $this->sync($server->node);
        } catch (\Throwable $exception) {
            $proxy->delete();
            $subdomain && $this->subdomains->delete($subdomain);

            throw $exception;
        }

        return $proxy;
    }

    /**
     * Removes the proxy from the node and its subdomain.
     *
     * @throws \Throwable
     */
    public function delete(HydraProxy $proxy, bool $sync = true): void
    {
        $node = $proxy->server->node;
        $subdomain = $proxy->subdomain;
        $proxy->delete();
        if ($sync) {
            $this->sync($node);
        }
        if ($subdomain) {
            $this->subdomains->delete($subdomain);
        }
    }

    /**
     * Sends every proxy of the node to it.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     * @throws DisplayException
     */
    public function sync(Node $node): void
    {
        $proxies = HydraProxy::query()
            ->with('allocation')
            ->whereHas('server', fn ($query) => $query->where('node_id', $node->id))
            ->get()
            ->map(fn (HydraProxy $proxy) => ['domain' => $proxy->domain, 'upstream' => $proxy->upstream()])
            ->values()
            ->all();

        $this->daemon->setNode($node)->sync($proxies);
    }

    /**
     * @throws DisplayException
     */
    public function validDomain(string $domain): string
    {
        $domain = strtolower(rtrim(trim($domain), '.'));
        if (strlen($domain) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $domain)) {
            throw new DisplayException(__('server/network.domain_invalid'));
        }

        return $domain;
    }

    /**
     * The certificate is only issued when the domain points to the node.
     *
     * @throws DisplayException
     */
    public function assertPointsToNode(string $domain, Node $node): void
    {
        $nodeIps = filter_var($node->fqdn, FILTER_VALIDATE_IP) ? [$node->fqdn] : self::resolve($node->fqdn);
        $domainIps = self::resolve($domain);

        if (empty($domainIps) || empty(array_intersect($domainIps, $nodeIps))) {
            throw new DisplayException(__('server/network.domain_not_pointing', [
                'domain' => $domain,
                'target' => $node->fqdn,
            ]));
        }
    }

    /**
     * Addresses of a name (A and AAAA, following CNAMEs).
     */
    public static function resolve(string $name): array
    {
        $records = @dns_get_record($name, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_unique(array_filter(array_map(fn (array $r) => $r['ip'] ?? $r['ipv6'] ?? null, $records))));
    }
}
