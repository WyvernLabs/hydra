<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\ServerLockdownService;
use Pterodactyl\Services\Hydra\Addons\LockdownAddon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: lockdown of a server from its "Manage" page in the admin area.
 */
class ServerLockdownController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private ServerLockdownService $service)
    {
    }

    public function __invoke(Request $request, Server $server): RedirectResponse
    {
        if (!app(LockdownAddon::class)->enabled()) {
            throw new NotFoundHttpException();
        }

        $lockdown = $request->validate(['lockdown' => 'required|boolean'])['lockdown'];

        if ((bool) $lockdown !== $server->isLockedDown()) {
            $lockdown ? $this->service->enable($server) : $this->service->disable($server);

            Activity::event($lockdown ? 'server:settings.lockdown-enable' : 'server:settings.lockdown-disable')
                ->subject($server)
                ->log();
        }

        $this->alert->success(__($lockdown ? 'admin/servers.manage.lockdown_enabled' : 'admin/servers.manage.lockdown_disabled'))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }
}
