<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Nodes\NodeSshInstaller;
use Pterodactyl\Services\Hydra\Nodes\NodeInstallService;

/**
 * Hydra: installs Hydra Wings on a node from the panel, over SSH or with a
 * short install command (Nodes > Installation).
 */
class NodeInstallController extends Controller
{
    public function __construct(private NodeInstallService $install, private NodeSshInstaller $ssh)
    {
    }

    public function index(Node $node): View
    {
        return view('admin.nodes.view.install', [
            'node' => $node,
            'available' => $this->install->available(),
            'certificate' => $this->install->usesCertificate($node),
        ]);
    }

    /**
     * A new one-time install command.
     */
    public function command(Request $request, Node $node): JsonResponse
    {
        if (!$this->install->available()) {
            return new JsonResponse(['message' => __('admin/nodes.install.not_available')], 422);
        }
        $code = $this->install->createCode($node, $request->user());

        return new JsonResponse(['command' => $this->install->command($code)]);
    }

    public function start(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9.:\-\[\]]+$/'],
            'port' => 'required|integer|between:1,65535',
            'username' => ['required', 'string', 'max:64', 'regex:/^[a-z_][a-z0-9_.-]*$/i'],
            'password' => 'nullable|string|max:1024',
            'private_key' => 'nullable|string|max:16384',
        ]);
        if (empty($data['password']) && empty($data['private_key'])) {
            return new JsonResponse(['message' => __('admin/nodes.install.ssh.no_credentials')], 422);
        }

        try {
            $this->ssh->start(
                $node,
                $request->user(),
                trim($data['host'], '[]'),
                (int) $data['port'],
                $data['username'],
                $data['password'] ?? null,
                $data['private_key'] ?? null,
            );
        } catch (DisplayException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return new JsonResponse($this->ssh->state($node));
    }

    public function status(Node $node): JsonResponse
    {
        return new JsonResponse($this->ssh->state($node));
    }

    public function forget(Node $node): Response
    {
        $this->ssh->forget($node);

        return response()->noContent();
    }
}
