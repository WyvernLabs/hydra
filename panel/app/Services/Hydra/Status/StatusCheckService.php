<?php

namespace Pterodactyl\Services\Hydra\Status;

use Pterodactyl\Models\Node;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\HydraStatusService;

/**
 * Hydra: checks the nodes and applications of the status page and counts the
 * results per hour. Everything is asked at once so that a target that is down
 * does not delay the others.
 */
class StatusCheckService
{
    public const TIMEOUT = 8;
    public const CONNECT_TIMEOUT = 5;
    public const TCP_TIMEOUT = 5;

    // Results are shown for 7 days.
    public const KEEP_DAYS = 8;

    /**
     * @return array<string, bool> online per target
     */
    public function run(): array
    {
        $nodes = Node::query()->get();
        $services = HydraStatusService::query()->where('enabled', true)->get();
        $http = $services->where('type', HydraStatusService::TYPE_HTTP);

        $responses = Http::pool(function (Pool $pool) use ($nodes, $http) {
            $requests = [];
            foreach ($nodes as $node) {
                // The wings API needs the token of the node, like DaemonRepository.
                $requests[] = $pool->as("node:$node->id")
                    ->withToken($node->getDecryptedKey())
                    ->acceptJson()
                    ->withOptions(['verify' => app()->environment('production')])
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->timeout(self::TIMEOUT)
                    ->get($node->getConnectionAddress() . '/api/system');
            }
            foreach ($http as $service) {
                $requests[] = $pool->as($service->statusKey())
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->timeout(self::TIMEOUT)
                    ->get($service->target);
            }

            return $requests;
        });

        $results = [];
        foreach ($nodes as $node) {
            $response = $responses["node:$node->id"] ?? null;
            $results["node:$node->id"] = $response instanceof Response && $response->successful();
        }
        foreach ($http as $service) {
            $response = $responses[$service->statusKey()] ?? null;
            // An application that answers is up, even when it asks for a login (401) or has no page there (404).
            $results[$service->statusKey()] = $response instanceof Response && $response->status() < 500;
        }
        foreach ($services->where('type', HydraStatusService::TYPE_TCP) as $service) {
            $results[$service->statusKey()] = $this->tcp($service->target);
        }

        $this->store($results);
        DB::table('hydra_status_checks')->where('hour', '<', now()->subDays(self::KEEP_DAYS))->delete();

        return $results;
    }

    /**
     * Connects to host:port, also [ipv6]:port.
     */
    private function tcp(string $target): bool
    {
        $position = strrpos($target, ':');
        if ($position === false) {
            return false;
        }

        $host = substr($target, 0, $position);
        $port = (int) substr($target, $position + 1);
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, self::TCP_TIMEOUT);
        if ($socket === false) {
            Log::debug("Status check of $target failed: $errorMessage");

            return false;
        }
        fclose($socket);

        return true;
    }

    /**
     * @param array<string, bool> $results
     */
    private function store(array $results): void
    {
        $hour = now()->startOfHour()->format('Y-m-d H:i:s');

        foreach ($results as $target => $online) {
            DB::table('hydra_status_checks')->upsert(
                ['target' => $target, 'hour' => $hour, 'checks' => 1, 'failures' => $online ? 0 : 1, 'online' => $online],
                ['target', 'hour'],
                [
                    'checks' => DB::raw('checks + 1'),
                    'failures' => DB::raw('failures + ' . ($online ? 0 : 1)),
                    'online' => $online,
                ],
            );
        }
    }
}
