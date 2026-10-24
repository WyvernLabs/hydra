<?php

namespace Pterodactyl\Services\Hydra\Users;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Pterodactyl\Models\HydraServerRole;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;
use Pterodactyl\Services\Servers\GetUserPermissionsService;

/**
 * Hydra: roles of a server for its subusers. A role holds permissions; the
 * subusers with the role get exactly those, also when the role changes.
 */
class ServerRoleService
{
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
        if ($user->canAccessAllServers() || $user->id === $server->owner_id) {
            return;
        }

        // Every subuser has the websocket, it is never checked.
        $own = array_merge($this->permissions->handle($server, $user), [Permission::ACTION_WEBSOCKET_CONNECT]);
        if (count(array_diff($permissions, $own)) > 0) {
            throw new HttpForbiddenException(__('server/users.role_not_grantable'));
        }
    }

    public function save(Server $server, ?HydraServerRole $role, array $data): HydraServerRole
    {
        $role ??= new HydraServerRole(['server_id' => $server->id]);
        $role->fill([
            'name' => trim($data['name']),
            'color' => strtolower($data['color']),
            'permissions' => self::clean($data['permissions'] ?? []),
        ])->saveOrFail();

        $this->apply($role);

        return $role;
    }

    /**
     * Gives the subusers of the role its permissions; their open sessions end
     * so the new ones take effect.
     */
    public function apply(HydraServerRole $role): void
    {
        $role->subusers()->with('user')->get()->each(function (Subuser $subuser) use ($role) {
            $current = $subuser->permissions ?? [];
            sort($current);
            if ($current === $role->permissions) {
                return;
            }

            $subuser->forceFill(['permissions' => $role->permissions])->save();
            RevokeSftpAccessJob::dispatch($subuser->user->uuid, $role->server);
        });
    }
}
