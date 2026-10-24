<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraServerRole;
use Pterodactyl\Services\Hydra\Users\ServerRoleService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Hydra: roles of a server for its subusers, managed on the users page.
 */
class ServerRoleController extends ClientApiController
{
    public function __construct(private ServerRoleService $service)
    {
        parent::__construct();
    }

    public function index(Request $request, Server $server): array
    {
        $this->assertCan($request, $server, Permission::ACTION_USER_READ);

        return [
            'data' => HydraServerRole::query()
                ->where('server_id', $server->id)
                ->withCount('subusers')
                ->orderBy('name')
                ->get()
                ->map(fn (HydraServerRole $role) => $this->role($role))
                ->all(),
        ];
    }

    public function store(Request $request, Server $server): array
    {
        $this->assertCan($request, $server, Permission::ACTION_USER_CREATE);
        $data = $this->validated($request, $server);
        if (HydraServerRole::query()->where('server_id', $server->id)->count() >= 50) {
            throw new AccessDeniedHttpException(__('server/users.role_limit'));
        }

        $role = $this->service->save($server, null, $data);
        Activity::event('server:role.create')->property('name', $role->name)->log();

        return $this->role($role->loadCount('subusers'));
    }

    public function update(Request $request, Server $server, int $role): array
    {
        $this->assertCan($request, $server, Permission::ACTION_USER_UPDATE);
        $model = $this->find($server, $role);
        $data = $this->validated($request, $server);
        // The members' permissions change too, so they have to be grantable as well.
        $this->service->assertAssignable($request->user(), $server, $model->permissions);

        $model = $this->service->save($server, $model, $data);
        Activity::event('server:role.update')->property('name', $model->name)->log();

        return $this->role($model->loadCount('subusers'));
    }

    public function delete(Request $request, Server $server, int $role): Response
    {
        $this->assertCan($request, $server, Permission::ACTION_USER_DELETE);
        $model = $this->find($server, $role);
        $this->service->assertAssignable($request->user(), $server, $model->permissions);

        // The members keep their permissions, without the role.
        $model->delete();
        Activity::event('server:role.delete')->property('name', $model->name)->log();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function validated(Request $request, Server $server): array
    {
        $data = $request->validate([
            'name' => 'required|string|between:1,60',
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'permissions' => 'present|array',
            'permissions.*' => 'string',
        ]);
        $this->service->assertAssignable($request->user(), $server, ServerRoleService::clean($data['permissions']));

        return $data;
    }

    private function find(Server $server, int $id): HydraServerRole
    {
        return HydraServerRole::query()->where('server_id', $server->id)->find($id)
            ?? throw new NotFoundHttpException();
    }

    private function assertCan(Request $request, Server $server, string $permission): void
    {
        if (!$request->user()->can($permission, $server)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function role(HydraServerRole $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'color' => $role->color,
            'permissions' => $role->permissions,
            'members' => (int) ($role->subusers_count ?? 0),
        ];
    }
}
