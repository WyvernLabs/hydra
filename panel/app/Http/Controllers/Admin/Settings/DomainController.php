<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\DomainService;
use Illuminate\Validation\ValidationException;

/**
 * Hydra: change the domain of the panel from the admin area.
 */
class DomainController extends Controller
{
    public function __construct(private DomainService $domains)
    {
    }

    public function index(): View
    {
        return view('admin.settings.domain', [
            'domain' => $this->domains->toArray(),
            'nodes' => Node::query()->orderBy('name')->get(['id', 'name', 'fqdn']),
        ]);
    }

    /**
     * State of the change, polled while the page is open.
     */
    public function status(): JsonResponse
    {
        return new JsonResponse($this->domains->toArray());
    }

    /**
     * Where a domain points, shown below the field while typing.
     */
    public function lookup(Request $request): JsonResponse
    {
        $domain = DomainService::normalize((string) $request->query('domain', ''));
        if (!DomainService::isValid($domain)) {
            return new JsonResponse(['valid' => false]);
        }

        $current = $this->domains->current()['domain'];

        return new JsonResponse([
            'valid' => true,
            'domain' => $domain,
            'ips' => $this->domains->resolve($domain),
            'panel_ips' => $current !== '' ? $this->domains->resolve($current) : [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->merge(['domain' => DomainService::normalize((string) $request->input('domain', ''))]);
        $current = $this->domains->current();

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'ssl' => ['required', Rule::in(DomainService::SSL_MODES)],
            'redirect_old' => ['sometimes', 'boolean'],
            'force' => ['sometimes', 'boolean'],
            'drop' => ['sometimes', 'array'],
            'drop.*' => ['string', Rule::in($current['redirects'])],
        ]);

        $domain = $data['domain'];
        if (!DomainService::isValid($domain)) {
            throw ValidationException::withMessages(['domain' => __('admin/settings.domain.invalid')]);
        }
        if ($data['ssl'] === 'on' && DomainService::isIp($domain)) {
            throw ValidationException::withMessages(['ssl' => __('admin/settings.domain.ssl_ip')]);
        }

        $force = (bool) ($data['force'] ?? false);
        if ($domain !== $current['domain'] && !$force && !DomainService::isIp($domain) && empty($this->domains->resolve($domain))) {
            throw ValidationException::withMessages(['domain' => __('admin/settings.domain.not_resolving', ['domain' => $domain])]);
        }

        if (!$this->domains->isAvailable()) {
            return new JsonResponse(['error' => __('admin/settings.domain.not_available')], JsonResponse::HTTP_CONFLICT);
        }
        if ($this->domains->isBusy()) {
            return new JsonResponse(['error' => __('admin/settings.domain.busy')], JsonResponse::HTTP_CONFLICT);
        }

        $this->domains->request($domain, $data['ssl'], (bool) ($data['redirect_old'] ?? false), $data['drop'] ?? [], $force);

        return new JsonResponse(['url' => DomainService::url($domain, $data['ssl'])], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * Points every node at the current panel URL again, e.g. after a node was
     * offline or Wings was updated.
     */
    public function syncNodes(): JsonResponse
    {
        return new JsonResponse(['nodes' => $this->domains->syncNodes()]);
    }
}
