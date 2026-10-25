<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Egg;

/**
 * Hydra: servers get the additional ports their egg needs automatically,
 * e.g. the raw, query and RCON port of ARK. Which ports an egg needs is set
 * on the egg (Nests > Egg > "Additional ports"), see EggPorts and
 * PortAssignmentService.
 */
class PortsAddon extends Addon
{
    public function key(): string
    {
        return 'ports';
    }

    public function icon(): string
    {
        return 'network';
    }

    public function summary(): array
    {
        return [trans('admin/addons.ports.summary', ['count' => $this->eggs()->count()])];
    }

    /**
     * Eggs that have additional ports.
     *
     * @return \Illuminate\Support\Collection<int, Egg>
     */
    public function eggs()
    {
        return Egg::query()->with('nest')->whereNotNull('hydra_ports')->orderBy('name')->get()
            ->filter(fn (Egg $egg) => !empty($egg->hydra_ports))
            ->values();
    }
}
