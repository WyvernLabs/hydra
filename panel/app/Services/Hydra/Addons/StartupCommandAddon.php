<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: users change the startup command of their servers on the startup
 * page (with the permission "startup.update") and can go back to the one of
 * the egg. Off by default, since hosts often want to keep it fixed.
 */
class StartupCommandAddon extends Addon
{
    public function key(): string
    {
        return 'startup_command';
    }

    public function icon(): string
    {
        return 'square-terminal';
    }
}
