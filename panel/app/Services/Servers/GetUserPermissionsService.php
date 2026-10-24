<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;

class GetUserPermissionsService
{
    /**
     * Returns the server specific permissions that a user has. This checks
     * if they are an admin or a subuser for the server. If no permissions are
     * found, an empty array is returned.
     */
    public function handle(Server $server, User $user): array
    {
        // Hydra: nobody but the owner and allowed admins may use a server in lockdown.
        if (!$server->lockdownAllows($user)) {
            return [];
        }

        if ($user->canAccessAllServers() || $user->id === $server->owner_id) {
            $permissions = ['*'];

            if ($user->canAccessAllServers()) {
                $permissions[] = 'admin.websocket.errors';
                $permissions[] = 'admin.websocket.install';
                $permissions[] = 'admin.websocket.transfer';
            }

            return $this->withSuspended($user, $permissions);
        }

        /** @var \Pterodactyl\Models\Subuser|null $subuserPermissions */
        $subuserPermissions = $server->subusers()->where('user_id', $user->id)->first();

        return $this->withSuspended($user, $subuserPermissions ? $subuserPermissions->permissions : []);
    }

    /**
     * Hydra: lets Wings know that this user may use the console of a suspended
     * server and start it. Only exact matches count for "admin.*" permissions,
     * so "*" does not include it.
     */
    private function withSuspended(User $user, array $permissions): array
    {
        if (!empty($permissions) && $user->canUseSuspendedServers()) {
            $permissions[] = 'admin.websocket.suspended';
        }

        return $permissions;
    }
}
