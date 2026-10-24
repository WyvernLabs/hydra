<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Services\Hydra\Addons\PlayerStatsAddon;
use Pterodactyl\Services\Hydra\Minecraft\PlayerStatsService;

/**
 * Hydra: counts the players of the Minecraft servers (addon "player_stats").
 * Runs every five minutes.
 */
class SamplePlayersCommand extends Command
{
    protected $signature = 'hydra:players:sample';

    protected $description = 'Counts the players online on the Minecraft Java servers of the player statistics addon.';

    public function handle(PlayerStatsAddon $addon, PlayerStatsService $service): int
    {
        if (!$addon->enabled() || empty($addon->eggIds())) {
            return self::SUCCESS;
        }

        $this->info(sprintf('%d server(s) answered.', $service->sample()));

        return self::SUCCESS;
    }
}
