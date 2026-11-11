<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\ServerExpiryService;

/**
 * Hydra: runtime of a server from its "Manage" page in the admin area.
 */
class ServerExpiryController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private ServerExpiryService $expiry)
    {
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'hydra_expires_at' => 'nullable|date_format:' . ServerExpiryService::INPUT_FORMAT,
        ], [], ['hydra_expires_at' => __('admin/servers.expiry.field')]);

        $expiresAt = $request->boolean('clear') ? null : ServerExpiryService::parse($data['hydra_expires_at'] ?? null);
        $this->expiry->set($server, $expiresAt);

        $this->alert->success(__($expiresAt ? 'admin/servers.expiry.saved' : 'admin/servers.expiry.cleared'))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }
}
