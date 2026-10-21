<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\NodeDatabaseService;
use Pterodactyl\Repositories\Wings\DaemonFirewallRepository;
use Pterodactyl\Exceptions\Service\Hydra\NodeFirewallException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: the UFW firewall of a node.
 */
class NodeFirewallController extends Controller
{
    public function __construct(private DaemonFirewallRepository $daemon)
    {
    }

    public function index(Node $node): View
    {
        return view('admin.nodes.view.firewall', ['node' => $node]);
    }

    /**
     * State and rules, loaded by the page.
     */
    public function state(Node $node): JsonResponse
    {
        return self::attempt(fn () => ['online' => true] + $this->daemon->setNode($node)->state());
    }

    public function install(Node $node): JsonResponse
    {
        return self::attempt(fn () => $this->daemon->setNode($node)->install());
    }

    public function enable(Node $node): JsonResponse
    {
        return self::attempt(fn () => $this->daemon->setNode($node)->enable());
    }

    public function disable(Node $node): JsonResponse
    {
        return self::attempt(fn () => $this->daemon->setNode($node)->disable());
    }

    public function store(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:allow,deny,reject,limit',
            'direction' => 'required|in:in,out',
            'protocol' => 'required|in:any,tcp,udp',
            'port' => ['nullable', 'string', 'max:64', 'regex:/^\d{1,5}(:\d{1,5})?(,\d{1,5}(:\d{1,5})?)*$/'],
            'address' => ['nullable', 'string', 'max:64', function ($attribute, $value, $fail) {
                if ($value !== 'any' && !NodeDatabaseService::isValidSource($value)) {
                    $fail(__('admin/nodes.firewall.invalid_address'));
                }
            }],
            'comment' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\pN .,:;_\/()+#@-]*$/u'],
        ]);

        return self::attempt(fn () => $this->daemon->setNode($node)->addRule([
            'action' => $data['action'],
            'direction' => $data['direction'],
            'protocol' => $data['protocol'],
            'port' => (string) ($data['port'] ?? ''),
            'address' => (string) ($data['address'] ?? 'any'),
            'comment' => (string) ($data['comment'] ?? ''),
        ]));
    }

    public function destroy(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate(['rule' => 'required|string|max:500']);

        return self::attempt(fn () => $this->daemon->setNode($node)->deleteRule($data['rule']));
    }

    /**
     * Runs a request to the node and answers with its result, or with the
     * translated error and the output of the failed command.
     */
    public static function attempt(\Closure $callback): JsonResponse
    {
        try {
            $result = $callback();
        } catch (NodeFirewallException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
                'code' => $exception->errorCode,
                'detail' => $exception->detail,
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DaemonConnectionException) {
            return new JsonResponse(['online' => false, 'error' => __('admin/nodes.errors.offline')], JsonResponse::HTTP_BAD_GATEWAY);
        }

        return is_array($result) ? new JsonResponse($result) : new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
