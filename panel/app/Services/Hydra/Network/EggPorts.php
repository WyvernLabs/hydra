<?php

namespace Pterodactyl\Services\Hydra\Network;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;

/**
 * Hydra: the additional ports an egg needs besides the primary one (addon
 * "ports"). Each port is {variable, offset, label, connect}:
 *
 *  - variable: egg variable that gets the port, e.g. QUERY_PORT (optional)
 *  - offset:   the port has to be the primary port plus this, e.g. 1 for the
 *              raw port of ARK (optional, any free port otherwise)
 *  - label:    shown as note of the allocation on the network page
 *  - connect:  players connect over this port, so the panel shows it as the
 *              address of the server instead of the primary port (ARK: query port)
 *
 * In the admin area they are written one per line like the docker images:
 * "QUERY_PORT|Query Port|connect", "+1|Raw Port" or "RAW_PORT+1|Raw Port".
 */
class EggPorts
{
    public const MAX = 20;

    /**
     * @return array<int, array{variable: ?string, offset: ?int, label: ?string, connect: bool}>
     */
    public static function of(?Egg $egg): array
    {
        return self::normalize($egg?->hydra_ports ?? []);
    }

    /**
     * Parses the lines of the admin form, null when there are none.
     */
    public static function parse(?string $input): ?array
    {
        $ports = [];
        foreach (preg_split('/\r?\n/', $input ?? '') as $line) {
            $parts = explode('|', trim($line));
            $spec = trim(array_shift($parts));
            $connect = !empty($parts) && strtolower(trim(end($parts))) === 'connect';
            if ($connect) {
                array_pop($parts);
            }
            $label = empty($parts) ? null : implode('|', $parts);

            if ($spec === '' || !preg_match('/^([A-Z0-9_]+)?(?:\s*\+\s*(\d+))?$/i', $spec, $match)) {
                continue;
            }
            $ports[] = [
                'variable' => ($match[1] ?? '') !== '' ? strtoupper($match[1]) : null,
                'offset' => isset($match[2]) && $match[2] !== '' ? (int) $match[2] : null,
                'label' => $label,
                'connect' => $connect,
            ];
        }
        $ports = self::normalize($ports);

        return empty($ports) ? null : $ports;
    }

    /**
     * Lines for the admin form.
     */
    public static function format(?Egg $egg): string
    {
        return implode(PHP_EOL, array_map(function (array $port) {
            $line = ($port['variable'] ?? '') . (is_null($port['offset']) ? '' : "+{$port['offset']}");
            if (!is_null($port['label']) || $port['connect']) {
                $line .= '|' . ($port['label'] ?? '');
            }

            return $line . ($port['connect'] ? '|connect' : '');
        }, self::of($egg)));
    }

    /**
     * Drops invalid entries, e.g. of an imported egg file. Only the first port
     * can be the one to connect over.
     */
    public static function normalize(mixed $ports): array
    {
        if (!is_array($ports)) {
            return [];
        }

        $result = [];
        $connect = false;
        foreach ($ports as $port) {
            if (!is_array($port)) {
                continue;
            }
            $variable = is_string($port['variable'] ?? null) && preg_match('/^[A-Z0-9_]{1,191}$/i', $port['variable'])
                ? strtoupper($port['variable']) : null;
            $offset = is_numeric($port['offset'] ?? null) && (int) $port['offset'] >= 1 && (int) $port['offset'] <= 1000
                ? (int) $port['offset'] : null;
            $label = is_string($port['label'] ?? null) && trim($port['label']) !== ''
                ? mb_substr(trim($port['label']), 0, 191) : null;
            if (is_null($variable) && is_null($offset)) {
                continue;
            }
            $isConnect = !$connect && !empty($port['connect']);
            $connect = $connect || $isConnect;
            $result[] = ['variable' => $variable, 'offset' => $offset, 'label' => $label, 'connect' => $isConnect];
        }

        return array_slice($result, 0, self::MAX);
    }

    /**
     * The allocation players connect over when the egg marks one of its ports
     * for it, null to use the primary allocation.
     */
    public static function connectAllocation(Server $server): ?Allocation
    {
        $port = collect(self::of($server->egg))->firstWhere('connect', true);
        if (!$port) {
            return null;
        }

        $primary = $server->allocation;
        if (!is_null($port['offset'])) {
            $number = $primary ? $primary->port + $port['offset'] : null;
        } else {
            $value = $server->variables->firstWhere('env_variable', $port['variable']);
            $number = (int) trim((string) ($value->server_value ?? $value->default_value ?? ''));
        }

        return $server->allocations->first(
            fn (Allocation $allocation) => $allocation->port === $number && (!$primary || $allocation->ip === $primary->ip)
        ) ?? $server->allocations->firstWhere('port', $number);
    }
}
