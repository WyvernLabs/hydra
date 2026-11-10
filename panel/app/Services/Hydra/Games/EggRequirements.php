<?php

namespace Pterodactyl\Services\Hydra\Games;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;

/**
 * Hydra: minimum CPU and memory of an egg (Design > Eggs). A server whose
 * limits are lower cannot switch to the egg in the game library. A limit of
 * 0 means unlimited and always meets the minimum.
 */
class EggRequirements
{
    /**
     * @return array{cpu: int|null, memory: int|null}
     */
    public static function of(Egg $egg): array
    {
        return [
            'cpu' => $egg->hydra_min_cpu ? (int) $egg->hydra_min_cpu : null,
            'memory' => $egg->hydra_min_memory ? (int) $egg->hydra_min_memory : null,
        ];
    }

    /**
     * What the server lacks for the egg, keyed by "cpu" and "memory", with
     * the needed and the available amount. Empty when everything is met.
     *
     * @return array<string, array{needed: int, available: int}>
     */
    public static function missing(Server $server, Egg $egg): array
    {
        $missing = [];
        $limits = ['cpu' => (int) $server->cpu, 'memory' => (int) $server->memory];

        foreach (self::of($egg) as $key => $needed) {
            if ($needed && $limits[$key] > 0 && $limits[$key] < $needed) {
                $missing[$key] = ['needed' => $needed, 'available' => $limits[$key]];
            }
        }

        return $missing;
    }

    public static function save(Egg $egg, ?int $cpu, ?int $memory): void
    {
        $egg->forceFill([
            'hydra_min_cpu' => $cpu > 0 ? $cpu : null,
            'hydra_min_memory' => $memory > 0 ? $memory : null,
        ])->save();
    }
}
