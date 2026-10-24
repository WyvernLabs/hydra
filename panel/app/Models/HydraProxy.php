<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a domain the node passes on to a port of a server with HTTPS (addon
 * "proxy"), e.g. a web map. The domain is the user's own or a subdomain.
 *
 * @property int $id
 * @property int $server_id
 * @property string $domain
 * @property int $allocation_id
 * @property int|null $subdomain_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Server $server
 * @property Allocation $allocation
 * @property HydraSubdomain|null $subdomain
 */
class HydraProxy extends Model
{
    public const RESOURCE_NAME = 'hydra_proxy';

    protected $table = 'hydra_proxies';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'domain', 'allocation_id', 'subdomain_id'];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'domain' => 'required|string|max:253',
        'allocation_id' => 'required|integer',
        'subdomain_id' => 'nullable|integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function subdomain(): BelongsTo
    {
        return $this->belongsTo(HydraSubdomain::class, 'subdomain_id');
    }

    /**
     * Address the node passes the requests on to.
     */
    public function upstream(): string
    {
        $ip = $this->allocation->ip;
        if ($ip === '0.0.0.0' || $ip === '::') {
            $ip = '127.0.0.1';
        }

        return (str_contains($ip, ':') ? "[$ip]" : $ip) . ':' . $this->allocation->port;
    }
}
