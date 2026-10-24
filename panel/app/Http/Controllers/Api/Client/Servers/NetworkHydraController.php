<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraProxy;
use Pterodactyl\Models\HydraDomain;
use Pterodactyl\Models\HydraSubdomain;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Addons\ProxyAddon;
use Pterodactyl\Services\Hydra\Network\ProxyService;
use Pterodactyl\Services\Hydra\Addons\SubdomainsAddon;
use Pterodactyl\Services\Hydra\Network\SubdomainService;
use Pterodactyl\Services\Hydra\Network\DedicatedPortService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: subdomains (addon "subdomains"), reverse proxies (addon "proxy") and
 * free ports on a dedicated node in the network tab of a server.
 */
class NetworkHydraController extends ClientApiController
{
    public function __construct(
        private SubdomainsAddon $subdomainsAddon,
        private ProxyAddon $proxyAddon,
        private SubdomainService $subdomains,
        private ProxyService $proxies,
        private DedicatedPortService $dedicated,
    ) {
        parent::__construct();
    }

    /**
     * Everything the network tab shows beside the ports.
     */
    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_ALLOCATION_READ);

        $domains = HydraDomain::query()->where('enabled', true)->orderBy('name')->get(['id', 'name']);

        return [
            'dedicated' => [
                'enabled' => $server->hydra_dedicated,
                'reserved' => $server->hydra_dedicated ? $this->dedicated->reserved($server) : [],
                'min_port' => DedicatedPortService::MIN_PORT,
            ],
            'subdomains' => [
                'enabled' => $this->subdomainsAddon->enabled(),
                'limit' => $this->subdomainsAddon->limit($server),
                'domains' => $domains,
                'services' => array_keys(HydraSubdomain::SRV_SERVICES),
                'items' => $server->subdomains()->with('domain')->where('for_proxy', false)->get()
                    ->map(fn (HydraSubdomain $s) => $this->subdomainToArray($s))->values(),
            ],
            'proxies' => [
                'enabled' => $this->proxyAddon->enabled(),
                'limit' => $this->proxyAddon->limit($server),
                // Own domains have to point here.
                'target' => $server->node->fqdn,
                'items' => $server->proxies()->with('allocation')->get()
                    ->map(fn (HydraProxy $p) => $this->proxyToArray($p))->values(),
            ],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function storeSubdomain(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        if (!$this->subdomainsAddon->enabled()) {
            throw new NotFoundHttpException();
        }
        $data = $request->validate([
            'domain' => 'required|integer',
            'name' => 'required|string|max:63',
            'allocation' => 'nullable|integer',
            'srv' => 'nullable|string|max:32',
        ]);

        if ($server->subdomains()->where('for_proxy', false)->count() >= $this->subdomainsAddon->limit($server)) {
            throw new DisplayException(__('server/network.subdomain_limit'));
        }

        /** @var HydraDomain $domain */
        $domain = HydraDomain::query()->findOrFail($data['domain']);
        $allocation = empty($data['allocation']) ? null : $server->allocations()->findOrFail($data['allocation']);

        $subdomain = $this->subdomains->create($server, $domain, $data['name'], $allocation, $data['srv'] ?? null);
        Activity::event('server:subdomain.create')->property('name', $subdomain->fqdn())->log();

        return $this->subdomainToArray($subdomain->load('domain'));
    }

    /**
     * @throws \Throwable
     */
    public function deleteSubdomain(Request $request, Server $server, int $subdomain): JsonResponse
    {
        $this->check($request, $server, Permission::ACTION_ALLOCATION_DELETE);
        /** @var HydraSubdomain $model */
        $model = $server->subdomains()->where('for_proxy', false)->findOrFail($subdomain);

        $name = $model->fqdn();
        $this->subdomains->delete($model);
        Activity::event('server:subdomain.delete')->property('name', $name)->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @throws \Throwable
     */
    public function storeProxy(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        if (!$this->proxyAddon->enabled()) {
            throw new NotFoundHttpException();
        }
        $data = $request->validate([
            'allocation' => 'required|integer',
            'type' => 'required|in:own,subdomain',
            'domain' => 'required_if:type,own|nullable|string|max:253',
            'domain_id' => 'required_if:type,subdomain|nullable|integer',
            'name' => 'required_if:type,subdomain|nullable|string|max:63',
        ]);

        if ($server->proxies()->count() >= $this->proxyAddon->limit($server)) {
            throw new DisplayException(__('server/network.proxy_limit'));
        }

        $allocation = $server->allocations()->findOrFail($data['allocation']);
        $managed = null;
        if ($data['type'] === 'subdomain') {
            if (!$this->subdomainsAddon->enabled()) {
                throw new DisplayException(__('server/network.domain_unavailable'));
            }
            /** @var HydraDomain $managed */
            $managed = HydraDomain::query()->findOrFail($data['domain_id']);
        }

        $proxy = $this->proxies->create($server, $allocation, $data['domain'] ?? null, $managed, $data['name'] ?? null);
        Activity::event('server:proxy.create')->property(['domain' => $proxy->domain, 'allocation' => $allocation->toString()])->log();

        return $this->proxyToArray($proxy->load('allocation'));
    }

    /**
     * @throws \Throwable
     */
    public function deleteProxy(Request $request, Server $server, int $proxy): JsonResponse
    {
        $this->check($request, $server, Permission::ACTION_ALLOCATION_DELETE);
        /** @var HydraProxy $model */
        $model = $server->proxies()->findOrFail($proxy);

        $domain = $model->domain;
        $this->proxies->delete($model);
        Activity::event('server:proxy.delete')->property('domain', $domain)->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    private function subdomainToArray(HydraSubdomain $subdomain): array
    {
        return [
            'id' => $subdomain->id,
            'name' => $subdomain->fqdn(),
            'allocation' => $subdomain->allocation_id,
            'srv' => $subdomain->srv_service,
            'created_at' => $subdomain->created_at->toAtomString(),
        ];
    }

    private function proxyToArray(HydraProxy $proxy): array
    {
        return [
            'id' => $proxy->id,
            'domain' => $proxy->domain,
            'allocation' => $proxy->allocation_id,
            'port' => $proxy->allocation->port,
            'managed' => !is_null($proxy->subdomain_id),
            'created_at' => $proxy->created_at->toAtomString(),
        ];
    }

    private function check(Request $request, Server $server, string $permission): void
    {
        if (!$request->user()->can($permission, $server)) {
            throw new AuthorizationException();
        }
    }
}
