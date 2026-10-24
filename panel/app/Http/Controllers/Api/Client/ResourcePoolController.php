<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\HydraResourcePool;
use Pterodactyl\Services\Hydra\Addons\ResourcePoolAddon;
use Pterodactyl\Services\Hydra\Pool\ResourcePoolService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Hydra: the resource pool of the logged in user (addon "resource_pool"), shown
 * on the dashboard. Users create, resize and delete their pool servers here.
 */
class ResourcePoolController extends ClientApiController
{
    public function __construct(private ResourcePoolService $service, private ResourcePoolAddon $addon)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        $pool = $this->service->poolOf($request->user());

        return ['pool' => $pool ? $this->service->overview($request->user(), $pool) : null];
    }

    public function store(Request $request): JsonResponse
    {
        $pool = $this->pool($request);
        $data = $request->validate([
            'name' => 'required|string|min:1|max:191',
            'egg' => 'required|integer',
            'location' => 'nullable|integer',
            'environment' => 'nullable|array',
            'environment.*' => 'nullable|string|max:2000',
        ] + ResourcePoolService::resourceRules());

        $server = $this->service->create($request->user(), $pool, $data);

        return new JsonResponse(['identifier' => $server->uuidShort, 'uuid' => $server->uuid], Response::HTTP_CREATED);
    }

    public function update(Request $request, string $identifier): array
    {
        $pool = $this->pool($request);
        if (!$this->addon->userMayEdit()) {
            throw new AccessDeniedHttpException(trans('client/pool.errors.edit_disabled'));
        }
        $data = $request->validate(ResourcePoolService::resourceRules());

        $this->service->update($request->user(), $pool, $this->server($request, $identifier), $data);

        return $this->index($request);
    }

    public function delete(Request $request, string $identifier): Response
    {
        $this->pool($request);
        if (!$this->addon->userMayDelete()) {
            throw new AccessDeniedHttpException(trans('client/pool.errors.delete_disabled'));
        }

        $this->service->delete($request->user(), $this->server($request, $identifier));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function pool(Request $request): HydraResourcePool
    {
        return $this->service->poolOf($request->user())
            ?? throw new NotFoundHttpException(trans('client/pool.errors.no_pool'));
    }

    /**
     * A pool server of the user, by UUID or short identifier.
     */
    private function server(Request $request, string $identifier): Server
    {
        $server = Server::query()
            ->where('owner_id', $request->user()->id)
            ->where('hydra_pool', true)
            ->where(fn ($query) => $query->where('uuid', $identifier)->orWhere('uuidShort', $identifier))
            ->first() ?? throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));

        // A suspended server stays as the admins left it.
        if ($server->isSuspended()) {
            throw new AccessDeniedHttpException(trans('client/pool.errors.suspended'));
        }

        return $server;
    }
}
