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
 * @property array|null $allowed_paths
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Server $server
 */
class HydraServerRole extends Model
{
    public const RESOURCE_NAME = 'hydra_server_role';

    protected $table = 'hydra_server_roles';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'name', 'color', 'permissions', 'allowed_paths'];

    protected $casts = [
        'server_id' => 'integer',
        'permissions' => 'array',
        'allowed_paths' => 'array',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'name' => 'required|string|between:1,60',
        'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'permissions' => 'present|array',
        'permissions.*' => 'string',
        'allowed_paths' => 'nullable|array|max:50',
        'allowed_paths.*' => 'string|max:255',
    ];

    /**
     * Hydra: the folders of the role, cleaned up; empty for the whole server.
     */
    public function paths(): array
    {
        return \Pterodactyl\Services\Files\AllowedPaths::clean($this->allowed_paths ?? []);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function subusers(): HasMany
    {
        return $this->hasMany(Subuser::class, 'hydra_role_id');
    }
}
