<?php

namespace Pterodactyl\Jobs\Hydra;

use Pterodactyl\Models\HydraModpackInstall;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Services\Hydra\Minecraft\Modpacks\ModpackInstallService;

/**
 * Hydra: installs a modpack on a server (addon "modpacks"). Runs on the worker
 * for long jobs; it is never retried, a failed install is shown to the user.
 */
class InstallModpackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public int $install)
    {
    }

    public function handle(ModpackInstallService $service): void
    {
        $install = HydraModpackInstall::query()->find($this->install);
        if ($install && $install->status === 'queued') {
            $service->run($install);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        HydraModpackInstall::query()->whereKey($this->install)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'error' => __('server/modpacks.errors.failed'), 'updated_at' => now()]);
    }
}
