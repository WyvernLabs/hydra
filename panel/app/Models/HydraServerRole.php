<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a role of a server, a named set of permissions given to subusers.
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string $color
 * @property array $permissions
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Server $server
 */
class HydraServerRole extends Model
{
    public const RESOURCE_NAME = 'hydra_server_role';

    protected $table = 'hydra_server_roles';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'name', 'color', 'permissions'];

    protected $casts = [
        'server_id' => 'integer',
        'permissions' => 'array',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'name' => 'required|string|between:1,60',
        'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'permissions' => 'present|array',
        'permissions.*' => 'string',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function subusers(): HasMany
    {
        return $this->hasMany(Subuser::class, 'hydra_role_id');
    }
}
