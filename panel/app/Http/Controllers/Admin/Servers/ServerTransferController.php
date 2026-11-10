<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Servers\TransferServerService;

class ServerTransferController extends Controller
{
    /**
     * ServerTransferController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private TransferServerService $transferService,
    ) {
    }

    /**
     * Starts a transfer of a server to a new node.
     *
     * @throws \Throwable
     */
    public function transfer(Request $request, Server $server): RedirectResponse
    {
        $validatedData = $request->validate([
            'node_id' => 'required|exists:nodes,id',
            'allocation_id' => 'required|bail|unique:servers|exists:allocations,id',
            'allocation_additional' => 'nullable',
        ]);

        try {
            // Hydra: the transfer itself lives in TransferServerService (also used by the game support).
            $this->transferService->handle(
                $server,
                (int) $validatedData['node_id'],
                (int) $validatedData['allocation_id'],
                array_map('intval', $validatedData['allocation_additional'] ?? []),
            );
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.servers.view.manage', $server->id);
        }

        $this->alert->success(trans('admin/server.alerts.transfer_started'))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }
}
