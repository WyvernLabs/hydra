<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Services\Hydra\Addons\StatusAddon;
use Pterodactyl\Services\Hydra\Status\StatusCheckService;

/**
 * Hydra: checks the targets of the status page (addon "status"). Runs every minute.
 */
class StatusCheckCommand extends Command
{
    protected $signature = 'hydra:status:check';

    protected $description = 'Checks the nodes and applications of the status page.';

    public function handle(StatusAddon $addon, StatusCheckService $service): int
    {
        if (!$addon->enabled()) {
            return self::SUCCESS;
        }

        foreach ($service->run() as $target => $online) {
            $this->line($target . ': ' . ($online ? 'online' : 'offline'));
        }

        return self::SUCCESS;
    }
}
