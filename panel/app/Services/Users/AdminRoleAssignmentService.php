<?php

namespace Pterodactyl\Services\Users;

use Pterodactyl\Models\User;
use Pterodactyl\Models\AdminRole;

class AdminRoleAssignmentService
{
    /**
     * Gives the user exactly the requested admin roles, as far as the acting
     * user may: roles with permissions the actor does not hold can neither be
     * given nor taken away, so they stay as they are.
     */
    public function handle(User $actor, User $user, array $roleIds): void
    {
        $current = $user->adminRoles()->pluck('admin_roles.id')->all();
        $requested = array_map('intval', $roleIds);

        $final = [];
        $roles = AdminRole::query()->whereIn('id', array_unique(array_merge($current, $requested)))->get();
        foreach ($roles as $role) {
            $wanted = $this->canAssign($actor, $role) ? in_array($role->id, $requested, true) : in_array($role->id, $current, true);
            if ($wanted) {
                $final[] = $role->id;
            }
        }

        $user->adminRoles()->sync($final);
        $user->flushAdminPermissions();
    }

    /**
     * Whether the actor may give this role to someone or take it away.
     */
    public function canAssign(User $actor, AdminRole $role): bool
    {
        return $actor->hasAdminPermission('roles.assign')
            && $actor->canGrantAdminPermissions(AdminRole::normalizePermissions($role->permissions));
    }
}
