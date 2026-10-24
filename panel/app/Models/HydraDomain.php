<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hydra: a domain on Cloudflare users can create subdomains of (addon
 * "subdomains"). The API token needs the permission "Zone > DNS > Edit".
 *
 * @property int $id
 * @property string $name
 * @property string $zone_id
 * @property string $api_token
 * @property bool $enabled
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class HydraDomain extends Model
{
    public const RESOURCE_NAME = 'hydra_domain';

    protected $table = 'hydra_domains';

    protected bool $immutableDates = true;

    protected $fillable = ['name', 'zone_id', 'api_token', 'enabled'];

    protected $hidden = ['api_token'];

    protected $casts = [
        'enabled' => 'bool',
        'api_token' => 'encrypted',
    ];

    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'zone_id' => 'required|string|max:64',
        'api_token' => 'required|string',
        'enabled' => 'boolean',
    ];

    public function subdomains(): HasMany
    {
        return $this->hasMany(HydraSubdomain::class, 'domain_id');
    }
}
