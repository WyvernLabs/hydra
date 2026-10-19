<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\AdminRole;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\AdminRoleFormRequest;

/**
 * Hydra: roles for the admin area.
 */
class RoleController extends Controller
{
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    /**
     * Lists all roles.
     */
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => AdminRole::query()->withCount('users')->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    /**
     * Shows the form for a new role.
     */
    public function create(Request $request): View
    {
        return view('admin.roles.edit', [
            'role' => null,
            'groups' => AdminRole::PERMISSIONS,
            'grantable' => $request->user()->adminPermissions(),
        ]);
    }

    /**
     * Shows a role with its permissions and members.
     */
    public function view(Request $request, AdminRole $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role->load('users'),
            'groups' => AdminRole::PERMISSIONS,
            'grantable' => $request->user()->adminPermissions(),
        ]);
    }

    /**
     * @throws DisplayException
     */
    public function store(AdminRoleFormRequest $request): RedirectResponse
    {
        $data = $request->roleData();
        $this->assertGrantable($request, $data['permissions']);

        $role = AdminRole::query()->create($data);
        $this->alert->success(__('admin/roles.notices.created', ['name' => $role->name]))->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * @throws DisplayException
     */
    public function update(AdminRoleFormRequest $request, AdminRole $role): RedirectResponse
    {
        $data = $request->roleData();
        $this->assertGrantable($request, $role->permissions);
        $this->assertGrantable($request, $data['permissions']);

        $role->update($data);
        $this->alert->success(__('admin/roles.notices.updated', ['name' => $role->name]))->flash();

        return redirect()->route('admin.roles.view', $role->id);
    }

    /**
     * @throws DisplayException
     */
    public function delete(Request $request, AdminRole $role): RedirectResponse
    {
        $this->assertGrantable($request, $role->permissions);

        $role->delete();
        $this->alert->success(__('admin/roles.notices.deleted', ['name' => $role->name]))->flash();

        return redirect()->route('admin.roles');
    }

    /**
     * A user may only hand out or change permissions they hold themselves, so
     * nobody can give themselves or others more than they have.
     *
     * @throws DisplayException
     */
    protected function assertGrantable(Request $request, array $permissions): void
    {
        if (!$request->user()->canGrantAdminPermissions(AdminRole::normalizePermissions($permissions))) {
            throw new DisplayException(__('admin/roles.exceptions.not_grantable'));
        }
    }
}
