<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;

/**
 * Hydra: owners (and admins with "servers.lockdown") can put a server into
 * lockdown, which throws out everyone else right away. While the addon is
 * off, lockdowns have no effect but stay stored.
 */
class LockdownAddon extends Addon
{
    public function key(): string
    {
        return 'lockdown';
    }

    public function icon(): string
    {
        return 'lock';
    }

    public function summary(): array
    {
        return [trans('admin/addons.lockdown.summary', ['count' => $this->lockedDownCount()])];
    }

    /**
     * Servers whose owner started a lockdown that has not been lifted yet.
     */
    public function lockedDownCount(): int
    {
        return Server::query()->whereNotNull('hydra_lockdown_at')->count();
    }
}
