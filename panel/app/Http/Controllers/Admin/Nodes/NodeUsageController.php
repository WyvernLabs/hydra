<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: live resource usage of a node (CPU, memory, disk, network).
 */
class NodeUsageController extends Controller
{
    public function __construct(private DaemonConfigurationRepository $repository)
    {
    }

    public function __invoke(Node $node): JsonResponse
    {
        $bandwidth = ['bandwidth_bps' => $node->hydra_bandwidth * 1_000_000 / 8];

        try {
            return new JsonResponse(['online' => true] + $this->repository->setNode($node)->getUsage() + $bandwidth);
        } catch (DaemonConnectionException) {
            return new JsonResponse(['online' => false] + $bandwidth);
        }
    }
}
