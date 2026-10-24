<?php

namespace Pterodactyl\Http\Controllers\Admin\Users;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\HydraResourcePool;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;

/**
 * Hydra: the resource pool of a user (addon "resource_pool"), set on their
 * page in the admin area. Cores, memory, disk and backup space are entered
 * in cores and GB and stored in percent and MiB.
 */
class ResourcePoolController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if (!$request->user()->outranks($user)) {
            throw new DisplayException(__('admin/roles.exceptions.user_outranks'));
        }

        $data = $request->validate([
            'servers' => 'nullable|integer|min:0|max:1000',
            'cpu' => 'required|numeric|min:0|max:1000',
            'memory' => 'required|numeric|min:0|max:16384',
            'disk' => 'required|numeric|min:0|max:1048576',
            'databases' => 'required|integer|min:0|max:10000',
            'ports' => 'required|integer|min:0|max:10000',
            'backups' => 'required|numeric|min:0|max:1048576',
            'subdomains' => 'required|integer|min:0|max:10000',
            'proxies' => 'required|integer|min:0|max:10000',
            'pool_servers' => 'nullable|array',
            'pool_servers.*' => 'integer',
        ]);

        if (!$request->boolean('active')) {
            $user->resourcePool()->delete();
            Server::query()->where('owner_id', $user->id)->update(['hydra_pool' => false]);
            $this->alert->success(__('admin/users.pool.removed'))->flash();

            return redirect()->route('admin.users.view', $user->id);
        }

        HydraResourcePool::query()->updateOrCreate(['user_id' => $user->id], [
            // Empty: no limit of servers.
            'servers' => isset($data['servers']) ? (int) $data['servers'] : null,
            'cpu' => (int) round($data['cpu'] * 100),
            'memory' => (int) round($data['memory'] * 1024),
            'disk' => (int) round($data['disk'] * 1024),
            'databases' => (int) $data['databases'],
            'ports' => (int) $data['ports'],
            'backups' => (int) round($data['backups'] * 1024),
            'subdomains' => (int) $data['subdomains'],
            'proxies' => (int) $data['proxies'],
        ]);

        $selected = array_map('intval', $data['pool_servers'] ?? []);
        Server::query()->where('owner_id', $user->id)->whereIn('id', $selected)->update(['hydra_pool' => true]);
        Server::query()->where('owner_id', $user->id)->whereNotIn('id', $selected)->update(['hydra_pool' => false]);

        $this->alert->success(__('admin/users.pool.saved'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }
}
