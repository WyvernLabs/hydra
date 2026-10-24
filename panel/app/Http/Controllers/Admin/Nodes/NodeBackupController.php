<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Models\SftpBackupTarget;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\SftpBackupsAddon;
use Pterodactyl\Services\Hydra\Backups\SftpTargetService;

/**
 * Hydra: new backups of the servers on a node can go to an SFTP server
 * instead of the node's disk (addon "sftp_backups").
 */
class NodeBackupController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private SftpTargetService $service,
        private SftpBackupsAddon $addon,
    ) {
    }

    public function index(Node $node): View
    {
        $target = $this->target($node);

        return view('admin.nodes.view.backups', [
            'node' => $node,
            'addon' => $this->addon,
            'target' => SftpTargetService::toArray($target->exists ? $target : null),
            'stored' => $target->exists ? $target->backups()->where('is_successful', true)->count() : 0,
        ]);
    }

    /**
     * Tests and saves the SFTP server.
     *
     * @throws \Throwable
     */
    public function update(Request $request, Node $node): RedirectResponse
    {
        $target = $this->target($node);
        $data = $request->validate(SftpTargetService::rules() + ['enabled' => 'sometimes|boolean']);
        if (empty($data['password']) && empty($data['private_key']) && !$target->password && !$target->private_key) {
            throw new DisplayException(__('server/backups.credentials_required'));
        }

        $target->enabled = $request->boolean('enabled');
        $result = $this->service->save($target, $data, $node);

        $this->alert->success(__('admin/nodes.backups.saved', [
            'fingerprint' => $result['fingerprint'] ?? '',
            'count' => count($result['files'] ?? []),
        ]))->flash();

        return redirect()->route('admin.nodes.view.backups', $node->id);
    }

    /**
     * Removes the SFTP server; new backups are stored on the node again.
     */
    public function delete(Node $node): RedirectResponse
    {
        $target = $this->target($node);
        if ($target->exists) {
            if ($target->backups()->where('is_successful', true)->exists()) {
                throw new DisplayException(__('admin/nodes.backups.in_use'));
            }
            $target->delete();
        }

        $this->alert->success(__('admin/nodes.backups.removed'))->flash();

        return redirect()->route('admin.nodes.view.backups', $node->id);
    }

    private function target(Node $node): SftpBackupTarget
    {
        return SftpBackupTarget::query()->firstOrNew(['node_id' => $node->id]);
    }
}
