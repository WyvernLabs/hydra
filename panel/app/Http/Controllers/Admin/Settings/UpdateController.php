<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\UpdateService;
use Pterodactyl\Repositories\Wings\DaemonUpdateRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: update the panel and the nodes from the admin area.
 */
class UpdateController extends Controller
{
    public function __construct(private UpdateService $updates, private DaemonUpdateRepository $daemon)
    {
    }

    public function index(): View
    {
        return view('admin.settings.updates', [
            'panel' => $this->updates->toArray(),
            'nodes' => Node::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * State of the panel update, polled while the page is open.
     */
    public function status(): JsonResponse
    {
        return new JsonResponse($this->updates->toArray());
    }

    public function check(): JsonResponse
    {
        return $this->request('check');
    }

    public function updatePanel(): JsonResponse
    {
        return $this->request('update');
    }

    /**
     * Version of a node and the state of its last update.
     */
    public function node(Node $node): JsonResponse
    {
        try {
            return new JsonResponse(['online' => true] + $this->daemon->setNode($node)->status());
        } catch (DaemonConnectionException) {
            return new JsonResponse(['online' => false]);
        }
    }

    public function updateNode(Node $node): JsonResponse
    {
        try {
            $this->daemon->setNode($node)->start();
        } catch (DaemonConnectionException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([], JsonResponse::HTTP_ACCEPTED);
    }

    private function request(string $action): JsonResponse
    {
        if (!$this->updates->isAvailable()) {
            return new JsonResponse(['error' => __('admin/settings.updates.not_available')], JsonResponse::HTTP_CONFLICT);
        }

        if ($this->updates->isBusy()) {
            return new JsonResponse(['error' => __('admin/settings.updates.busy')], JsonResponse::HTTP_CONFLICT);
        }

        $this->updates->request($action);

        return new JsonResponse([], JsonResponse::HTTP_ACCEPTED);
    }
}
