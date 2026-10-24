<?php

namespace Pterodactyl\Console\Commands\Hydra;

use Illuminate\Console\Command;
use Pterodactyl\Services\Hydra\Tickets\TicketService;

/**
 * Hydra: removes ticket images that were uploaded but never sent. Scheduled
 * hourly; runs even while the tickets addon is off.
 */
class CleanTicketImagesCommand extends Command
{
    protected $signature = 'hydra:tickets:clean';

    protected $description = 'Removes images of tickets that were uploaded but never sent (tickets addon).';

    public function handle(TicketService $service): int
    {
        $this->info('Removed images: ' . $service->cleanup());

        return self::SUCCESS;
    }
}
