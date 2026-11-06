<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\HydraServerTemplate;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Templates\ServerTemplateService;

/**
 * Hydra: creates a server from a template, only its name and owner are entered.
 */
class ServerFromTemplateController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private ServerTemplateService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.servers.templates.deploy', [
            'templates' => HydraServerTemplate::query()->with('egg.nest')->orderBy('name')->get(),
            'selected' => (int) old('template_id', $request->query('template')),
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:1|max:191',
            'owner_id' => 'required|integer|exists:users,id',
            'template_id' => 'required|integer|exists:hydra_server_templates,id',
        ]);

        $template = HydraServerTemplate::query()->with('egg.variables')->findOrFail($data['template_id']);
        $server = $this->service->deploy($template, $data['name'], (int) $data['owner_id']);

        $this->alert->success(trans('admin/server.alerts.server_created'))->flash();

        return redirect()->route('admin.servers.view', $server->id);
    }
}
