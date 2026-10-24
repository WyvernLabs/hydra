<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a subdomain of a server on a Cloudflare domain, pointing to the node
 * and, with an SRV record, to a port of the server.
 *
 * @property int $id
 * @property int $server_id
 * @property int $domain_id
 * @property string $name
 * @property int|null $allocation_id
 * @property string|null $srv_service
 * @property array $records
 * @property bool $for_proxy
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property HydraDomain $domain
 * @property Server $server
 * @property Allocation|null $allocation
 */
class HydraSubdomain extends Model
{
    public const RESOURCE_NAME = 'hydra_subdomain';

    /**
     * SRV services users can pick, as service => protocol.
     */
    public const SRV_SERVICES = [
        'minecraft' => 'tcp',
        'ts3' => 'udp',
    ];

    protected $table = 'hydra_subdomains';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'domain_id', 'name', 'allocation_id', 'srv_service', 'records', 'for_proxy'];

    protected $casts = [
        'records' => 'array',
        'for_proxy' => 'bool',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'domain_id' => 'required|integer',
        'name' => 'required|string|max:63',
        'allocation_id' => 'nullable|integer',
        'srv_service' => 'nullable|string|max:32',
        'records' => 'array',
        'for_proxy' => 'boolean',
    ];

    public function fqdn(): string
    {
        return $this->name . '.' . $this->domain->name;
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(HydraDomain::class, 'domain_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function proxy(): HasOne
    {
        return $this->hasOne(HydraProxy::class, 'subdomain_id');
    }
}
