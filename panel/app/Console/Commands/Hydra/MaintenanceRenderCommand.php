<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Services\Hydra\MaintenancePageService;

/**
 * Hydra: renders the maintenance page (Design > Maintenance page) into the
 * active maintenance mode. The installer calls it during an update once the
 * new version is installed, so visitors see the current page and the admin
 * area can still follow the progress.
 */
class MaintenanceRenderCommand extends Command
{
    protected $signature = 'hydra:maintenance:render';

    protected $description = 'Renders the maintenance page into the active maintenance mode.';

    public function handle(MaintenancePageService $page): int
    {
        $this->info($page->refreshDownFile() ? 'Maintenance page rendered.' : 'The panel is not in maintenance mode.');

        return self::SUCCESS;
    }
}
