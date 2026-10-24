<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Hydra\Addons\PlayerStatsAddon;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Hydra\Minecraft\PlayerStatsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: players online over time for the server dashboard (addon "player_stats").
 */
class PlayerStatsController extends ClientApiController
{
    public function __construct(private PlayerStatsAddon $addon, private PlayerStatsService $service)
    {
        parent::__construct();
    }

    public function __invoke(Request $request, Server $server): array
    {
        if (!$this->addon->tracks($server)) {
            throw new NotFoundHttpException();
        }

        $range = $request->query('range', 'day');

        return $this->service->history($server, is_string($range) && isset(PlayerStatsService::RANGES[$range]) ? $range : 'day');
    }
}
