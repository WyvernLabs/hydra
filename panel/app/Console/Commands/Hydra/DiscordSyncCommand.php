<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Models\HydraDiscordLink;
use Pterodactyl\Services\Hydra\Addons\DiscordAddon;
use Pterodactyl\Services\Hydra\Discord\DiscordApiException;
use Pterodactyl\Services\Hydra\Discord\DiscordRoleService;

/**
 * Hydra: keeps the Discord roles of linked users in line with their servers and
 * panel roles (addon "discord"). Also picks up users who joined Discord later.
 */
class DiscordSyncCommand extends Command
{
    protected $signature = 'hydra:discord:sync';

    protected $description = 'Updates the Discord roles of all linked users.';

    public function handle(DiscordAddon $addon, DiscordRoleService $roles): int
    {
        if (!$addon->enabled()) {
            return self::SUCCESS;
        }

        HydraDiscordLink::query()->with('user')->chunkById(100, function ($links) use ($roles) {
            foreach ($links as $link) {
                try {
                    $roles->sync($link);
                } catch (DiscordApiException $exception) {
                    $this->warn("$link->discord_id: " . $exception->getMessage());
                }
            }
        });

        return self::SUCCESS;
    }
}
