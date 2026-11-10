<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Pterodactyl\Enum\JwtScope;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Services\Hydra\Support\SupportTabs;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Servers\TransferServerService;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Hydra: game support (addon "gamesupport", Community Management > Game
 * support). Staff open servers by their UUID in tabs that close after four
 * hours; each tab shows the console, the usage and power buttons through the
 * websocket of the node.
 */
class SupportController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private SupportTabs $tabs,
        private NodeJWTService $jwtService,
        private SuspensionService $suspension,
        private ReinstallServerService $reinstallService,
        private TransferServerService $transferService,
        private NodeRepository $nodes,
    ) {
    }

    public function index(Request $request): View
    {
        $tabs = $this->tabs->all();
        $servers = Server::query()
            ->with(['node', 'user', 'allocation', 'allocations', 'egg', 'transfer'])
            ->whereIn('id', array_keys($tabs))
            ->get()
            ->keyBy('id');

        // Servers deleted in the meantime drop out.
        $open = [];
        foreach ($tabs as $id => $openedAt) {
            if ($server = $servers->get($id)) {
                $open[] = ['server' => $server, 'expires' => $openedAt->addMinutes(SupportTabs::LIFETIME_MINUTES)];
            } else {
                $this->tabs->forget($id);
            }
        }

        $user = $request->user();

        return view('admin.support.index', [
            'tabs' => $open,
            'can' => [
                'suspend' => $user->hasAdminPermission('support.suspend'),
                'reinstall' => $user->hasAdminPermission('support.reinstall'),
                'transfer' => $user->hasAdminPermission('support.transfer'),
            ],
            // Other nodes with their free allocations, for the transfer.
            'transferNodes' => $user->hasAdminPermission('support.transfer') && count($open) > 0
                ? $this->nodes->getNodesForServerCreation()->values()->all()
                : [],
            // A server is only shown when one was picked; otherwise the search is shown.
            'active' => (int) $request->query('tab', 0),
            'canControl' => $request->user()->hasAdminPermission('support.control'),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate(['server' => 'required|string|max:255']);

        $server = SupportTabs::find($data['server']);
        if (!$server) {
            $this->alert->danger(__('admin/support.not_found'))->flash();

            return redirect()->route('admin.support')->withInput();
        }

        if (!$this->tabs->isOpen($server)) {
            // The owner sees in the activity log that staff looked at the server.
            Activity::event('server:support.open')->subject($server)->log();
        }
        $this->tabs->open($server);

        return redirect()->route('admin.support', ['tab' => $server->id]);
    }

    public function close(Server $server): JsonResponse
    {
        $this->tabs->close($server);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * A websocket token for an open tab. Viewing needs "support.view", the
     * power buttons and commands "support.control".
     */
    public function websocket(Request $request, Server $server): JsonResponse
    {
        if (!$this->tabs->isOpen($server)) {
            throw new AccessDeniedHttpException(__('admin/support.expired'));
        }

        $user = $request->user();
        if (!$server->lockdownAllows($user)) {
            throw new AccessDeniedHttpException(__('admin/support.lockdown'));
        }

        $permissions = ['websocket.connect', 'admin.websocket.errors', 'admin.websocket.install'];
        if ($user->hasAdminPermission('support.control')) {
            array_push($permissions, 'control.console', 'control.start', 'control.stop', 'control.restart');
        }
        if ($user->hasAdminPermission('servers.suspended')) {
            $permissions[] = 'admin.websocket.suspended';
        }

        $node = $server->node;
        $token = $this->jwtService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes(10))
            ->setUser($user)
            ->setClaims(['server_uuid' => $server->uuid, 'permissions' => $permissions])
            ->setScopes(JwtScope::Websocket)
            ->handle($node, $user->id . $server->uuid);

        $socket = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $node->getConnectionAddress());

        return new JsonResponse([
            'token' => $token->toString(),
            'socket' => $socket . sprintf('/api/servers/%s/ws', $server->uuid),
            'expires_at' => $this->tabs->expiresAt($server)?->toIso8601String(),
        ]);
    }

    /**
     * Hydra: suspends or unsuspends the server ("support.suspend").
     */
    public function suspension(Request $request, Server $server): JsonResponse
    {
        $this->assertOpen($server);
        $action = $request->validate(['action' => ['required', Rule::in([SuspensionService::ACTION_SUSPEND, SuspensionService::ACTION_UNSUSPEND])]])['action'];

        $this->suspension->toggle($server, $action);
        Activity::event("server:support.$action")->subject($server)->log();

        return new JsonResponse(['message' => __("admin/support.done.$action")]);
    }

    /**
     * Hydra: reinstalls the server ("support.reinstall").
     */
    public function reinstall(Server $server): JsonResponse
    {
        $this->assertOpen($server);
        if (!is_null($server->transfer)) {
            throw new DisplayException(__('admin/support.transfer_running'));
        }

        $this->reinstallService->handle($server);
        Activity::event('server:support.reinstall')->subject($server)->log();

        return new JsonResponse(['message' => __('admin/support.done.reinstall')]);
    }

    /**
     * Hydra: moves the server to another node ("support.transfer").
     *
     * @throws \Throwable
     */
    public function transfer(Request $request, Server $server): JsonResponse
    {
        $this->assertOpen($server);
        $data = $request->validate([
            'node_id' => ['required', 'integer', 'exists:nodes,id', Rule::notIn([$server->node_id])],
            'allocation_id' => 'required|integer',
            'allocation_additional' => 'nullable|array',
            'allocation_additional.*' => 'integer',
        ]);

        // Only free allocations of the chosen node.
        $wanted = array_values(array_unique(array_merge([(int) $data['allocation_id']], array_map('intval', $data['allocation_additional'] ?? []))));
        $free = Allocation::query()->where('node_id', $data['node_id'])->whereNull('server_id')->whereIn('id', $wanted)->pluck('id')->all();
        if (count($free) !== count($wanted)) {
            throw new DisplayException(__('admin/support.allocation_taken'));
        }

        $transfer = $this->transferService->handle(
            $server,
            (int) $data['node_id'],
            (int) $data['allocation_id'],
            array_values(array_diff($wanted, [(int) $data['allocation_id']])),
        );
        Activity::event('server:support.transfer')
            ->subject($server)
            ->property(['from' => $transfer->oldNode?->name, 'to' => $transfer->newNode?->name])
            ->log();

        return new JsonResponse(['message' => __('admin/support.done.transfer')]);
    }

    /**
     * Actions only work in an open tab, like the console.
     */
    private function assertOpen(Server $server): void
    {
        if (!$this->tabs->isOpen($server)) {
            throw new AccessDeniedHttpException(__('admin/support.expired'));
        }
    }
}
