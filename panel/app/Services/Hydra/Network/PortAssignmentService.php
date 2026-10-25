<?php

namespace Pterodactyl\Services\Hydra\Network;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\PortsAddon;

/**
 * Hydra: gives a server the additional ports of its egg (addon "ports") and
 * writes them into the egg variables, e.g. QUERY_PORT and RCON_PORT of ARK.
 *
 * Ports the server already has are reused, so running it again changes
 * nothing. Ports with an offset have to sit exactly that far above the
 * primary port on the same IP. Call it inside a transaction: when the node
 * has no fitting port left it throws and nothing is assigned.
 */
class PortAssignmentService
{
    public function __construct(private PortsAddon $addon)
    {
    }

    /**
     * @param bool $movePrimary pick another primary port when the one of the
     *                          server leaves no room for the ports with an
     *                          offset (automatic deployment only)
     *
     * @throws DisplayException
     */
    public function assign(Server $server, bool $movePrimary = false): void
    {
        $specs = $this->addon->enabled() ? EggPorts::of($server->egg()->first()) : [];
        if (empty($specs)) {
            return;
        }

        /** @var Allocation $primary */
        $primary = Allocation::query()->lockForUpdate()->findOrFail($server->allocation_id);
        $owned = Allocation::query()->where('server_id', $server->id)->where('id', '!=', $primary->id)->orderBy('port')->get();

        if (!$this->offsetsFit($server, $primary, $specs)) {
            if (!$movePrimary || !($other = $this->findPrimary($server, $primary, $specs))) {
                throw new DisplayException(trans('admin/addons.ports.no_offset_room', ['port' => $primary->port]));
            }
            $primary->forceFill(['server_id' => null])->save();
            $other->forceFill(['server_id' => $server->id])->save();
            $server->forceFill(['allocation_id' => $other->id])->save();
            $primary = $other;
        }

        $values = ServerVariable::query()
            ->join('egg_variables', 'egg_variables.id', '=', 'server_variables.variable_id')
            ->where('server_variables.server_id', $server->id)
            ->pluck('server_variables.variable_value', 'egg_variables.env_variable');

        // Ports with an offset first, so no other port takes their place.
        uksort($specs, fn (int $a, int $b) => [is_null($specs[$a]['offset']), $a] <=> [is_null($specs[$b]['offset']), $b]);

        $taken = [];
        $result = [];
        foreach ($specs as $index => $spec) {
            if (!is_null($spec['offset'])) {
                $result[$index] = $this->offsetAllocation($server, $primary, $spec['offset'])
                    ?? throw new DisplayException(trans('admin/addons.ports.no_offset_room', ['port' => $primary->port]));
                $taken[] = $result[$index]->id;
            }
        }
        // Keep ports the server already has for the variable.
        foreach ($specs as $index => $spec) {
            if (isset($result[$index]) || is_null($spec['variable'])) {
                continue;
            }
            $current = trim((string) ($values[$spec['variable']] ?? ''));
            $match = $owned->first(fn (Allocation $a) => (string) $a->port === $current && !in_array($a->id, $taken, true));
            if ($match) {
                $result[$index] = $match;
                $taken[] = $match->id;
            }
        }
        foreach ($specs as $index => $spec) {
            if (isset($result[$index])) {
                continue;
            }
            $result[$index] = $owned->first(fn (Allocation $a) => !in_array($a->id, $taken, true))
                ?? $this->freeAllocation($primary, $taken)
                ?? throw new DisplayException(trans('admin/addons.ports.no_free_port', ['label' => $spec['label'] ?? $spec['variable']]));
            $taken[] = $result[$index]->id;
        }

        foreach ($result as $index => $allocation) {
            $spec = $specs[$index];
            $allocation->forceFill([
                'server_id' => $server->id,
                'notes' => $allocation->notes ?: $spec['label'],
            ])->save();

            if (!is_null($spec['variable'])) {
                $variable = EggVariable::query()->where('egg_id', $server->egg_id)->where('env_variable', $spec['variable'])->first();
                if ($variable) {
                    ServerVariable::query()->updateOrCreate(
                        ['server_id' => $server->id, 'variable_id' => $variable->id],
                        ['variable_value' => (string) $allocation->port],
                    );
                }
            }
        }
    }

    /**
     * Whether every port with an offset is free (or already the server's).
     */
    private function offsetsFit(Server $server, Allocation $primary, array $specs): bool
    {
        foreach ($specs as $spec) {
            if (!is_null($spec['offset']) && !$this->offsetAllocation($server, $primary, $spec['offset'])) {
                return false;
            }
        }

        return true;
    }

    private function offsetAllocation(Server $server, Allocation $primary, int $offset): ?Allocation
    {
        return Allocation::query()
            ->where('node_id', $primary->node_id)
            ->where('ip', $primary->ip)
            ->where('port', $primary->port + $offset)
            ->where(fn ($query) => $query->whereNull('server_id')->orWhere('server_id', $server->id))
            ->lockForUpdate()
            ->first();
    }

    /**
     * A free port of the node that leaves room for the ports with an offset.
     */
    private function findPrimary(Server $server, Allocation $current, array $specs): ?Allocation
    {
        $candidates = Allocation::query()
            ->where('node_id', $current->node_id)
            ->whereNull('server_id')
            ->orderByRaw('ip = ? desc', [$current->ip])
            ->orderBy('port')
            ->lockForUpdate()
            ->get();

        return $candidates->first(fn (Allocation $candidate) => $this->offsetsFit($server, $candidate, $specs));
    }

    /**
     * A free port of the node, preferably on the IP of the primary port and
     * right above it.
     */
    private function freeAllocation(Allocation $primary, array $taken): ?Allocation
    {
        return Allocation::query()
            ->where('node_id', $primary->node_id)
            ->whereNull('server_id')
            ->whereNotIn('id', $taken)
            ->orderByRaw('ip = ? desc', [$primary->ip])
            ->orderByRaw('port > ? desc', [$primary->port])
            ->orderBy('port')
            ->lockForUpdate()
            ->first();
    }
}
