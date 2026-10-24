<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\HydraDomain;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Network\CloudflareClient;

/**
 * Hydra: Cloudflare domains users can create subdomains of (addon
 * "subdomains").
 */
class DomainController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.design.domains', [
            'domains' => HydraDomain::query()->withCount('subdomains')->orderBy('name')->get(),
        ]);
    }

    /**
     * Adds a domain once its zone is found with the token.
     *
     * @throws DisplayException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/i', 'unique:hydra_domains,name'],
            'api_token' => 'required|string|max:255',
        ]);
        $name = strtolower($data['name']);

        $zone = (new CloudflareClient($data['api_token']))->zoneId($name);
        HydraDomain::query()->create(['name' => $name, 'zone_id' => $zone, 'api_token' => $data['api_token'], 'enabled' => true]);

        $this->alert->success(__('admin/domains.added', ['name' => $name]))->flash();

        return redirect()->route('admin.design.domains');
    }

    /**
     * Turns a domain on or off for new subdomains, or replaces its token.
     *
     * @throws DisplayException
     */
    public function update(Request $request, HydraDomain $domain): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'api_token' => 'nullable|string|max:255',
        ]);

        if (!empty($data['api_token'])) {
            $domain->zone_id = (new CloudflareClient($data['api_token']))->zoneId($domain->name);
            $domain->api_token = $data['api_token'];
        }
        $domain->enabled = (bool) $data['enabled'];
        $domain->save();

        $this->alert->success(__('admin/domains.updated', ['name' => $domain->name]))->flash();

        return redirect()->route('admin.design.domains');
    }

    /**
     * Removes a domain without subdomains.
     *
     * @throws DisplayException
     */
    public function delete(HydraDomain $domain): RedirectResponse
    {
        if ($domain->subdomains()->exists()) {
            throw new DisplayException(__('admin/domains.in_use'));
        }

        $domain->delete();
        $this->alert->success(__('admin/domains.deleted', ['name' => $domain->name]))->flash();

        return redirect()->route('admin.design.domains');
    }
}
