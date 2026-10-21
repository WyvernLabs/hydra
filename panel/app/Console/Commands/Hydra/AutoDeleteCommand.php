<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Services\Hydra\AutoDeleteService;
use Pterodactyl\Services\Hydra\Users\SanctionService;

/**
 * Hydra: lifts expired bans and runs the autodelete addon. Scheduled hourly
 * (expired bans every five minutes with --bans-only).
 */
class AutoDeleteCommand extends Command
{
    protected $signature = 'hydra:autodelete {--bans-only : Only lift bans whose time is up}';

    protected $description = 'Lifts expired bans and deletes long suspended servers and banned accounts (autodelete addon).';

    public function handle(SanctionService $sanctions, AutoDeleteService $autodelete): int
    {
        $lifted = $sanctions->liftExpired();
        $this->info("Expired bans lifted: $lifted");

        if ($this->option('bans-only')) {
            return self::SUCCESS;
        }

        $result = $autodelete->run();
        $this->info(sprintf('Deleted servers: %d, deleted accounts: %d, failed: %d', $result['servers'], $result['users'], $result['failed']));

        return self::SUCCESS;
    }
}
