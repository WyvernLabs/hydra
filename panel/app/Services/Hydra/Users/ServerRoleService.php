<?php

namespace Pterodactyl\Services\Hydra\Users;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Pterodactyl\Models\HydraServerRole;
use Pterodactyl\Services\Files\AllowedPaths;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;
use Pterodactyl\Services\Servers\GetUserPermissionsService;

/**
 * Hydra: roles of a server for its subusers. A role holds permissions and
 * optionally folders; the subusers with the role get exactly those, also when
 * the role changes.
 */
class ServerRoleService
{
    public const MAX_ROLES = 50;

    public function __construct(private GetUserPermissionsService $permissions)
    {
    }

    /**
     * Known permissions only, plus the websocket every subuser needs.
     */
    public static function clean(array $permissions): array
    {
        $known = Permission::permissions()
            ->map(fn ($value, $prefix) => array_map(fn ($key) => "$prefix.$key", array_keys($value['keys'])))
            ->flatten()
            ->all();

        $cleaned = array_values(array_unique(array_merge(array_intersect($permissions, $known), [Permission::ACTION_WEBSOCKET_CONNECT])));
        sort($cleaned);

        return $cleaned;
    }

    /**
     * Nobody can hand out permissions they do not have themselves.
     *
     * @throws HttpForbiddenException
     */
    public function assertAssignable(User $user, Server $server, array $permissions): void
    {
        if (!$this->canAssign($user, $server, $permissions)) {
            throw new HttpForbiddenException(__('server/users.role_not_grantable'));
        }
    }

    /**
     * A user limited to certain folders can only hand out those folders; an
     * empty list (whole server) needs access to the whole server.
     *
     * @throws HttpForbiddenException
     */
    public function assertPathsAssignable(User $user, Server $server, array $paths): void
    {
        if (!AllowedPaths::for($server, $user)->covers(new AllowedPaths($paths))) {
            throw new HttpForbiddenException(__('server/files.paths_not_grantable'));
        }
    }

    public function canAssign(User $user, Server $server, array $permissions): bool
    {
        if ($user->canAccessAllServers() || $user->id === $server->owner_id) {
            return true;
        }

        // Every subuser has the websocket, it is never checked.
        $own = array_merge($this->permissions->handle($server, $user), [Permission::ACTION_WEBSOCKET_CONNECT]);

        return count(array_diff($permissions, $own)) === 0;
    }

    public function save(Server $server, ?HydraServerRole $role, array $data): HydraServerRole
    {
        $paths = AllowedPaths::clean($data['allowed_paths'] ?? []);

        $role ??= new HydraServerRole(['server_id' => $server->id]);
        $role->fill([
            'name' => trim($data['name']),
            'color' => strtolower($data['color']),
            'permissions' => self::clean($data['permissions'] ?? []),
            'allowed_paths' => empty($paths) ? null : $paths,
        ])->saveOrFail();

        $this->apply($role);

        return $role;
    }

    /**
     * Gives the subusers of the role its permissions and folders; their open
     * sessions end so the new ones take effect.
     */
    public function apply(HydraServerRole $role): void
    {
        $paths = $role->paths();

        $role->subusers()->with('user')->get()->each(function (Subuser $subuser) use ($role, $paths) {
            $current = $subuser->permissions ?? [];
            sort($current);
            $currentPaths = AllowedPaths::clean($subuser->allowed_paths ?? []);
            if ($current === $role->permissions && $currentPaths === $paths) {
                return;
            }

            $subuser->forceFill([
                'permissions' => $role->permissions,
                'allowed_paths' => empty($paths) ? null : $paths,
            ])->save();
            RevokeSftpAccessJob::dispatch($subuser->user->uuid, $role->server);
        });
    }

    /**
     * Servers the user may copy roles to: their own ones and those where they
     * may manage subusers, without the given server.
     */
    public function copyTargets(User $user, Server $except): array
    {
        return $user->accessibleServers()
            ->where('servers.id', '!=', $except->id)
            ->orderBy('servers.name')
            ->get()
            ->filter(fn (Server $server) => $user->can(Permission::ACTION_USER_CREATE, $server) && $user->can(Permission::ACTION_USER_UPDATE, $server))
            ->values()
            ->all();
    }

    /**
     * Copies a role to another server. A role with the same name there is
     * replaced (its members get the new rights), otherwise a new one is made.
     *
     * @return string "created", "updated" or the reason it was skipped
     *                ("forbidden", "permissions", "folders", "limit")
     */
    public function copy(User $user, HydraServerRole $role, Server $target): string
    {
        if (!$user->can(Permission::ACTION_USER_CREATE, $target) || !$user->can(Permission::ACTION_USER_UPDATE, $target)) {
            return 'forbidden';
        }

        if (!$this->canAssign($user, $target, $role->permissions)) {
            return 'permissions';
        }

        if (!AllowedPaths::for($target, $user)->covers(new AllowedPaths($role->paths()))) {
            return 'folders';
        }

        /** @var HydraServerRole|null $existing */
        $existing = HydraServerRole::query()->where('server_id', $target->id)->where('name', $role->name)->first();
        if ($existing && !$this->canAssign($user, $target, $existing->permissions)) {
            // Replacing it changes its members, so its current rights count as well.
            return 'permissions';
        }

        if (!$existing && HydraServerRole::query()->where('server_id', $target->id)->count() >= self::MAX_ROLES) {
            return 'limit';
        }

        $this->save($target, $existing, [
            'name' => $role->name,
            'color' => $role->color,
            'permissions' => $role->permissions,
            'allowed_paths' => $role->paths(),
        ]);

        return $existing ? 'updated' : 'created';
    }
}
