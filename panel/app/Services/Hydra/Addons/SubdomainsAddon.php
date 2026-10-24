<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\HydraDomain;

/**
 * Hydra: users create subdomains of Cloudflare domains for their servers, in
 * the network tab, with an SRV record for games like Minecraft. Domains are
 * managed in the admin area under Design > Addons > Subdomains > Domains.
 */
class SubdomainsAddon extends Addon
{
    // Names that are never given out, in addition to the admin's list.
    public const ALWAYS_BLOCKED = ['www', 'mail', 'ftp', 'smtp', 'imap', 'pop', 'ns1', 'ns2', 'panel', 'admin', 'api', 'webmail', 'autoconfig', 'autodiscover'];

    public function key(): string
    {
        return 'subdomains';
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function rules(): array
    {
        return [
            'default_limit' => 'required|integer|min:0|max:100',
            'blocked' => 'nullable|string|max:5000',
        ];
    }

    public function missing(): ?string
    {
        return HydraDomain::query()->where('enabled', true)->exists() ? null : trans('admin/addons.subdomains.missing');
    }

    public function summary(): array
    {
        return [
            trans('admin/addons.subdomains.summary', [
                'domains' => HydraDomain::query()->where('enabled', true)->count(),
                'limit' => $this->defaultLimit(),
            ]),
        ];
    }

    public function defaultLimit(): int
    {
        return (int) $this->get('default_limit', 1);
    }

    /**
     * Subdomains the server may have.
     */
    public function limit(Server $server): int
    {
        return $server->hydra_subdomain_limit ?? $this->defaultLimit();
    }

    /**
     * @return string[]
     */
    public function blocked(): array
    {
        $own = preg_split('/[\s,]+/', strtolower((string) $this->get('blocked', ''))) ?: [];

        return array_values(array_unique(array_filter(array_merge(self::ALWAYS_BLOCKED, $own))));
    }
}
