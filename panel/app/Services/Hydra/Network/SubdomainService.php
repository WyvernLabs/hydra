<?php

namespace Pterodactyl\Services\Hydra\Network;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\HydraDomain;
use Pterodactyl\Models\HydraSubdomain;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\SubdomainsAddon;

/**
 * Hydra: subdomains of servers on Cloudflare domains (addon "subdomains").
 * The name points to the node (A record for a public address, otherwise a
 * CNAME to the node's domain); an SRV record can point to a port.
 */
class SubdomainService
{
    public function __construct(private SubdomainsAddon $addon)
    {
    }

    /**
     * Normalized name, or an exception when it cannot be used.
     *
     * @throws DisplayException
     */
    public function validName(string $name): string
    {
        $name = strtolower(trim($name));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $name)) {
            throw new DisplayException(__('server/network.subdomain_invalid'));
        }
        if (in_array($name, $this->addon->blocked(), true)) {
            throw new DisplayException(__('server/network.subdomain_blocked', ['name' => $name]));
        }

        return $name;
    }

    /**
     * Creates the DNS records and the subdomain.
     *
     * @throws DisplayException
     */
    public function create(Server $server, HydraDomain $domain, string $name, ?Allocation $allocation, ?string $srv, bool $forProxy = false): HydraSubdomain
    {
        $name = $this->validName($name);
        if (!$domain->enabled) {
            throw new DisplayException(__('server/network.domain_unavailable'));
        }
        if (HydraSubdomain::query()->where('domain_id', $domain->id)->where('name', $name)->exists()) {
            throw new DisplayException(__('server/network.subdomain_taken', ['name' => "$name.$domain->name"]));
        }
        if ($allocation && $allocation->server_id !== $server->id) {
            throw new DisplayException(__('server/network.allocation_invalid'));
        }
        if (!is_null($srv) && !array_key_exists($srv, HydraSubdomain::SRV_SERVICES)) {
            throw new DisplayException(__('server/network.srv_invalid'));
        }

        $fqdn = "$name.$domain->name";
        $client = new CloudflareClient($domain->api_token);
        // Records made by hand in Cloudflare are never replaced.
        if (!empty($client->records($domain->zone_id, $fqdn))) {
            throw new DisplayException(__('server/network.subdomain_taken', ['name' => $fqdn]));
        }

        $records = [];
        try {
            $records[] = $client->create($domain->zone_id, self::addressRecord($fqdn, $allocation, $server->node) + ['proxied' => false]);
            if ($srv && $allocation) {
                $records[] = $client->create($domain->zone_id, [
                    'type' => 'SRV',
                    'name' => sprintf('_%s._%s.%s', $srv, HydraSubdomain::SRV_SERVICES[$srv], $fqdn),
                    'data' => ['priority' => 0, 'weight' => 5, 'port' => $allocation->port, 'target' => $fqdn],
                ]);
            }
        } catch (DisplayException $exception) {
            foreach ($records as $id) {
                try {
                    $client->delete($domain->zone_id, $id);
                } catch (\Throwable) {
                }
            }

            throw $exception;
        }

        /** @var HydraSubdomain $subdomain */
        $subdomain = HydraSubdomain::query()->create([
            'server_id' => $server->id,
            'domain_id' => $domain->id,
            'name' => $name,
            'allocation_id' => $allocation?->id,
            'srv_service' => $allocation ? $srv : null,
            'records' => $records,
            'for_proxy' => $forProxy,
        ]);

        return $subdomain;
    }

    /**
     * Deletes the DNS records and the subdomain.
     *
     * @throws DisplayException
     */
    public function delete(HydraSubdomain $subdomain): void
    {
        $domain = $subdomain->domain;
        $client = new CloudflareClient($domain->api_token);
        foreach ($subdomain->records ?? [] as $id) {
            $client->delete($domain->zone_id, (string) $id);
        }

        $subdomain->delete();
    }

    /**
     * The A or CNAME record of the name: the address of the port when it is
     * public, otherwise the domain (or address) of the node.
     */
    public static function addressRecord(string $fqdn, ?Allocation $allocation, Node $node): array
    {
        $public = fn (?string $ip) => $ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        $ip = $allocation?->ip;
        if (!$public($ip)) {
            $ip = filter_var($node->fqdn, FILTER_VALIDATE_IP) ? $node->fqdn : null;
        }
        if ($ip && $public($ip)) {
            return ['type' => str_contains($ip, ':') ? 'AAAA' : 'A', 'name' => $fqdn, 'content' => $ip];
        }

        return ['type' => 'CNAME', 'name' => $fqdn, 'content' => $node->fqdn];
    }
}
