<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Models\AdminRole;

class AdminRoleFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $role = $this->route()->parameter('role');

        return $role instanceof AdminRole
            ? AdminRole::getRulesForUpdate($role)
            : AdminRole::getRules();
    }

    /**
     * The role data from the form, with the permissions cleaned up.
     */
    public function roleData(): array
    {
        return [
            'name' => $this->input('name'),
            'color' => strtolower($this->input('color')),
            'description' => $this->input('description'),
            'sort' => (int) $this->input('sort', 0),
            'permissions' => AdminRole::normalizePermissions($this->input('permissions') ?? []),
        ];
    }
}
