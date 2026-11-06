<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: resources a user may share out to own servers (addon "resource_pool").
 * CPU is stored in percent of a core, memory, disk and backup space in MiB.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $servers
 * @property int $cpu
 * @property int $memory
 * @property int $disk
 * @property int $databases
 * @property int $ports
 * @property int $backups
 * @property int $subdomains
 * @property int $proxies
 * @property int[]|null $locations locations the servers may go to (public nodes of them)
 * @property int[]|null $nodes nodes the servers may go to, also non-public ones
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property User $user
 */
class HydraResourcePool extends Model
{
    public const RESOURCE_NAME = 'hydra_resource_pool';

    /**
     * Resources a server takes from the pool, in the order they are shown.
     */
    public const RESOURCES = ['cpu', 'memory', 'disk', 'databases', 'ports', 'backups', 'subdomains', 'proxies'];

    protected $table = 'hydra_resource_pools';

    protected bool $immutableDates = true;

    protected $fillable = ['user_id', 'servers', 'cpu', 'memory', 'disk', 'databases', 'ports', 'backups', 'subdomains', 'proxies', 'locations', 'nodes'];

    protected $casts = [
        'user_id' => 'integer',
        'servers' => 'integer',
        'cpu' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'databases' => 'integer',
        'ports' => 'integer',
        'backups' => 'integer',
        'subdomains' => 'integer',
        'proxies' => 'integer',
        'locations' => 'array',
        'nodes' => 'array',
    ];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'servers' => 'nullable|integer|min:0|max:1000',
        'cpu' => 'required|integer|min:0|max:100000',
        'memory' => 'required|integer|min:0|max:16777216',
        'disk' => 'required|integer|min:0|max:1073741824',
        'databases' => 'required|integer|min:0|max:10000',
        'ports' => 'required|integer|min:0|max:10000',
        'backups' => 'required|integer|min:0|max:1073741824',
        'subdomains' => 'required|integer|min:0|max:10000',
        'proxies' => 'required|integer|min:0|max:10000',
        'locations' => 'nullable|array',
        'locations.*' => 'integer',
        'nodes' => 'nullable|array',
        'nodes.*' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * True when the admin set own locations or nodes for this pool, which
     * then replace the locations of the addon settings.
     */
    public function hasOwnTargets(): bool
    {
        return !empty($this->locations) || !empty($this->nodes);
    }

    /**
     * @return array<string, int|null> the limit of every resource and of servers (null = no limit)
     */
    public function limits(): array
    {
        return ['servers' => $this->servers] + array_combine(self::RESOURCES, array_map(fn (string $key) => (int) $this->{$key}, self::RESOURCES));
    }
}
