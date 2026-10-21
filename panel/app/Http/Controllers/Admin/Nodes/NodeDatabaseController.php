<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\NodeDatabaseService;
use Pterodactyl\Repositories\Wings\DaemonFirewallRepository;

/**
 * Hydra: set up MariaDB on a node as database host in one click.
 */
class NodeDatabaseController extends Controller
{
    public function __construct(private NodeDatabaseService $service, private DaemonFirewallRepository $daemon)
    {
    }

    public function index(Node $node): View
    {
        $sources = $this->service->extraSources();
        if (empty($sources)) {
            // The address the panel connects to the node from.
            try {
                $ip = $this->daemon->setNode($node)->state()['client_ip'] ?? null;
                if (is_string($ip) && NodeDatabaseService::isValidSource($ip)) {
                    $sources = [$ip];
                }
            } catch (\Throwable) {
            }
        }

        $host = $this->service->host($node);
        $nodes = Node::query()->orderBy('name')->get(['id', 'name', 'fqdn']);
        $addresses = $this->service->nodeAddresses();

        return view('admin.nodes.view.database', [
            'node' => $node,
            'host' => $host,
            'nodes' => $nodes,
            'addresses' => $addresses,
            'defaults' => [
                'name' => $host->name ?? $node->name,
                'host' => $host->host ?? $this->service->defaultHost($node),
                'port' => $host->port ?? 3306,
                'sources' => implode("\n", $sources),
            ],
        ]);
    }

    public function status(Node $node): JsonResponse
    {
        return NodeFirewallController::attempt(fn () => ['online' => true] + $this->service->status($node) + [
            'host' => $this->service->host($node)?->only(['id', 'name', 'host', 'port', 'username']),
        ]);
    }

    public function setup(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'host' => 'required|string|max:191|regex:/^[\w\-\.]+$/',
            'port' => 'required|integer|between:1,65535',
            'sources' => ['nullable', 'string', 'max:5000', function ($attribute, $value, $fail) {
                foreach (NodeDatabaseService::parseSources((string) $value) as $source) {
                    if (!NodeDatabaseService::isValidSource($source)) {
                        $fail(__('admin/nodes.database.invalid_source', ['source' => $source]));

                        return;
                    }
                }
            }],
            'enable_firewall' => 'sometimes|boolean',
        ]);

        return NodeFirewallController::attempt(fn () => $this->service->start($node, [
            'name' => $data['name'],
            'host' => $data['host'],
            'port' => (int) $data['port'],
            'sources' => NodeDatabaseService::parseSources((string) ($data['sources'] ?? '')),
            'enable_firewall' => $request->boolean('enable_firewall'),
        ]));
    }

    /**
     * Opens the database port again for every node, e.g. after allocations
     * with new addresses were added.
     */
    public function sync(): JsonResponse
    {
        $errors = $this->service->syncFirewall();

        return new JsonResponse(['errors' => (object) $errors]);
    }
}
