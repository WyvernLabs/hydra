<?php

namespace Pterodactyl\Services\Servers;

use Carbon\CarbonImmutable;
use Pterodactyl\Enum\JwtScope;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerTransfer;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Wings\DaemonTransferRepository;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;

/**
 * Hydra: starts the transfer of a server to another node. Moved out of the
 * ServerTransferController so the game support area can use it as well.
 */
class TransferServerService
{
    public function __construct(
        private AllocationRepositoryInterface $allocationRepository,
        private ConnectionInterface $connection,
        private DaemonTransferRepository $daemonTransferRepository,
        private NodeJWTService $nodeJWTService,
        private NodeRepository $nodeRepository,
    ) {
    }

    /**
     * @param int[] $additionalAllocations
     *
     * @throws DisplayException when the node has not enough memory or disk left
     * @throws \Throwable
     */
    public function handle(Server $server, int $nodeId, int $allocationId, array $additionalAllocations = []): ServerTransfer
    {
        // Check if the node is viable for the transfer.
        $node = $this->nodeRepository->getNodeWithResourceUsage($nodeId);
        if (!$node->isViable($server->memory, $server->disk)) {
            throw new DisplayException(trans('admin/server.alerts.transfer_not_viable'));
        }

        $server->validateTransferState();

        return $this->connection->transaction(function () use ($server, $nodeId, $allocationId, $additionalAllocations) {
            // Create a new ServerTransfer entry.
            $transfer = new ServerTransfer();

            $transfer->server_id = $server->id;
            $transfer->old_node = $server->node_id;
            $transfer->new_node = $nodeId;
            $transfer->old_allocation = $server->allocation_id;
            $transfer->new_allocation = $allocationId;
            $transfer->old_additional_allocations = $server->allocations->where('id', '!=', $server->allocation_id)->pluck('id')->values()->toArray();
            $transfer->new_additional_allocations = $additionalAllocations;

            $transfer->save();

            // Add the allocations to the server, so they cannot be automatically assigned while the transfer is in progress.
            $this->assignAllocationsToServer($server, $nodeId, $allocationId, $additionalAllocations);

            // Generate a token for the destination node that the source node can use to authenticate with.
            $token = $this->nodeJWTService
                ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                ->setSubject($server->uuid)
                ->setScopes(JwtScope::ServerTransfer)
                ->handle($transfer->newNode, $server->uuid);

            // Notify the source node of the pending outgoing transfer.
            $this->daemonTransferRepository->setServer($server)->notify($transfer->newNode, $token);

            return $transfer;
        });
    }

    /**
     * Assigns the specified allocations to the specified server.
     */
    private function assignAllocationsToServer(Server $server, int $nodeId, int $allocationId, array $additionalAllocations): void
    {
        $allocations = $additionalAllocations;
        $allocations[] = $allocationId;

        $unassigned = $this->allocationRepository->getUnassignedAllocationIds($nodeId);

        $updateIds = [];
        foreach ($allocations as $allocation) {
            if (in_array($allocation, $unassigned)) {
                $updateIds[] = $allocation;
            }
        }

        if (!empty($updateIds)) {
            $this->allocationRepository->updateWhereIn('id', $updateIds, ['server_id' => $server->id]);
        }
    }
}
