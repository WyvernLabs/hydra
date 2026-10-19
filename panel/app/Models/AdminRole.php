<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Hydra: a role for the admin area. Users holding a role get every admin
 * permission stored on it; root admins always have all of them.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property string|null $description
 * @property array $permissions
 * @property int $sort
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|User[] $users
 */
class AdminRole extends Model
{
    public const RESOURCE_NAME = 'admin_role';

    /**
     * Every permission of the admin area, grouped by area. The keys are stored
     * as "area.action", their titles live in lang/<locale>/admin/roles.php.
     *
     * "view" opens an area and is needed for every other action of that area.
     */
    public const PERMISSIONS = [
        'settings' => ['view', 'update'],
        'design' => ['view', 'update'],
        'api' => ['view', 'create', 'delete'],
        'databases' => ['view', 'create', 'update', 'delete'],
        'locations' => ['view', 'create', 'update', 'delete'],
        'nodes' => ['view', 'create', 'update', 'allocations', 'configuration', 'delete'],
        'servers' => ['view', 'create', 'details', 'build', 'startup', 'databases', 'manage', 'transfer', 'delete', 'access'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete', 'assign'],
        'nests' => ['view', 'create', 'update', 'delete'],
        'eggs' => ['view', 'create', 'update', 'import', 'delete'],
    ];

    /**
     * Permissions that only make sense together with another one, e.g. eggs
     * are reached through the nest pages.
     */
    public const IMPLIES = [
        'eggs.view' => 'nests.view',
        'roles.assign' => 'users.update',
    ];

    protected $table = 'admin_roles';

    protected $fillable = ['name', 'color', 'description', 'permissions', 'sort'];

    protected $casts = [
        'permissions' => 'array',
        'sort' => 'integer',
    ];

    protected $attributes = [
        'color' => '#74c236',
        'permissions' => '[]',
        'sort' => 0,
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,64|unique:admin_roles,name',
        'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'description' => 'nullable|string|max:500',
        'permissions' => 'array',
        'permissions.*' => 'string',
        'sort' => 'integer|min:0',
    ];

    /**
     * Returns every admin permission key, e.g. "servers.view".
     *
     * @return string[]
     */
    public static function allPermissions(): array
    {
        $list = [];
        foreach (self::PERMISSIONS as $area => $actions) {
            foreach ($actions as $action) {
                $list[] = "$area.$action";
            }
        }

        return $list;
    }

    /**
     * Drops unknown keys and adds the "view" permission of every area that
     * has another permission, since nothing in an area works without it.
     * Permissions from IMPLIES are added the same way.
     *
     * @return string[]
     */
    public static function normalizePermissions(array $permissions): array
    {
        $permissions = array_values(array_intersect(self::allPermissions(), $permissions));

        for ($i = 0; $i < count($permissions); ++$i) {
            $needed = [explode('.', $permissions[$i])[0] . '.view'];
            if (isset(self::IMPLIES[$permissions[$i]])) {
                $needed[] = self::IMPLIES[$permissions[$i]];
            }

            foreach ($needed as $permission) {
                if (!in_array($permission, $permissions)) {
                    $permissions[] = $permission;
                }
            }
        }

        return array_values(array_intersect(self::allPermissions(), $permissions));
    }

    /**
     * A text color that is readable on top of the role color.
     */
    public function textColor(): string
    {
        $hex = ltrim($this->color, '#');
        [$r, $g, $b] = array_map('hexdec', str_split(strlen($hex) === 6 ? $hex : '74c236', 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#111827' : '#ffffff';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_role_user');
    }
}
