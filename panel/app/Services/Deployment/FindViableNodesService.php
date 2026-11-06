<?php

namespace Pterodactyl\Services\Deployment;

use Pterodactyl\Models\Node;
use Webmozart\Assert\Assert;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException;

class FindViableNodesService
{
    protected array $locations = [];
    protected array $nodes = [];
    protected ?int $disk = null;
    protected ?int $memory = null;

    /**
     * Set the locations that should be searched through to locate available nodes.
     */
    public function setLocations(array $locations): self
    {
        Assert::allIntegerish($locations, 'An array of location IDs should be provided when calling setLocations.');

        $this->locations = $locations;

        return $this;
    }

    /**
     * Hydra: nodes that may be picked besides the public nodes of the locations,
     * also when they are not public (e.g. nodes dedicated to a user). With
     * nodes but no locations only these nodes are searched.
     */
    public function setNodes(array $nodes): self
    {
        Assert::allIntegerish($nodes, 'An array of node IDs should be provided when calling setNodes.');

        $this->nodes = $nodes;

        return $this;
    }

    /**
     * Set the amount of disk that will be used by the server being created. Nodes will be
     * filtered out if they do not have enough available free disk space for this server
     * to be placed on.
     */
    public function setDisk(int $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set the amount of memory that this server will be using. As with disk space, nodes that
     * do not have enough free memory will be filtered out.
     */
    public function setMemory(int $memory): self
    {
        $this->memory = $memory;

        return $this;
    }

    /**
     * Returns an array of nodes that meet the provided requirements and can then
     * be passed to the AllocationSelectionService to return a single allocation.
     *
     * This functionality is used for automatic deployments of servers and will
     * attempt to find all nodes in the defined locations that meet the disk and
     * memory availability requirements. Any nodes not meeting those requirements
     * are tossed out, as are any nodes marked as non-public, meaning automatic
     * deployments should not be done against them.
     *
     * @param int|null $page If provided the results will be paginated by returning
     *                       up to 50 nodes at a time starting at the provided page.
     *                       If "null" is provided as the value no pagination will
     *                       be used.
     *
     * @throws NoViableNodeException
     */
    public function handle(?int $perPage = null, ?int $page = null): LengthAwarePaginator|Collection
    {
        Assert::integer($this->disk, 'Disk space must be an int, got %s');
        Assert::integer($this->memory, 'Memory usage must be an int, got %s');

        $query = Node::query()->select('nodes.*')
            ->selectRaw('IFNULL(SUM(servers.memory), 0) as sum_memory')
            ->selectRaw('IFNULL(SUM(servers.disk), 0) as sum_disk')
            ->leftJoin('servers', 'servers.node_id', '=', 'nodes.id');

        if (empty($this->nodes)) {
            $query->where('nodes.public', 1);
            if (!empty($this->locations)) {
                $query->whereIn('nodes.location_id', $this->locations);
            }
        } else {
            $query->where(function ($query) {
                $query->whereIn('nodes.id', $this->nodes);
                if (!empty($this->locations)) {
                    $query->orWhere(fn ($query) => $query->where('nodes.public', 1)->whereIn('nodes.location_id', $this->locations));
                }
            });
        }

        $results = $query->groupBy('nodes.id')
            ->havingRaw('(IFNULL(SUM(servers.memory), 0) + ?) <= (nodes.memory * (1 + (nodes.memory_overallocate / 100)))', [$this->memory])
            ->havingRaw('(IFNULL(SUM(servers.disk), 0) + ?) <= (nodes.disk * (1 + (nodes.disk_overallocate / 100)))', [$this->disk]);

        if (!is_null($page)) {
            $results = $results->paginate($perPage ?? 50, ['*'], 'page', $page);
        } else {
            $results = $results->get()->toBase();
        }

        if ($results->isEmpty()) {
            throw new NoViableNodeException(trans('exceptions.deployment.no_viable_nodes'));
        }

        return $results;
    }
}
