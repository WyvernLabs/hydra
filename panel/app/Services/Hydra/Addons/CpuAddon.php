<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: shows the CPU usage of servers out of 100 % instead of 100 % per
 * core, e.g. 60 % of a 300 % limit becomes 20 %. Only servers with a CPU
 * limit can be converted; unlimited ones keep the usual value.
 */
class CpuAddon extends Addon
{
    public function key(): string
    {
        return 'cpu';
    }

    public function icon(): string
    {
        return 'cpu';
    }
}
