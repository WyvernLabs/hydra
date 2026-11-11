<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Services\Hydra\ServerExpiryService;

/**
 * Hydra: suspends servers whose runtime is over. Scheduled every minute.
 */
class ExpireServersCommand extends Command
{
    protected $signature = 'hydra:servers:expire';

    protected $description = 'Suspends servers whose runtime has expired.';

    public function handle(ServerExpiryService $expiry): int
    {
        $result = $expiry->expire();
        $this->info(sprintf('Suspended servers: %d, failed: %d', $result['suspended'], $result['failed']));

        return self::SUCCESS;
    }
}
