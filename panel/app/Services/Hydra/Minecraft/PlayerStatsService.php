<?php

namespace Pterodactyl\Services\Hydra\Minecraft;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Hydra\Addons\PlayerStatsAddon;

/**
 * Hydra: counts the players of Minecraft Java servers (addon "player_stats")
 * and hands out their course for the server dashboard.
 */
class PlayerStatsService
{
    public const TABLE = 'hydra_player_samples';

    // Length and width of the bars of every range, in seconds.
    public const RANGES = [
        'day' => [86400, 300],
        'week' => [604800, 3600],
        'month' => [2592000, 14400],
    ];

    public function __construct(private PlayerStatsAddon $addon, private ServerListPing $ping)
    {
    }

    /**
     * Asks every server of the chosen eggs for its players and removes
     * samples older than the days to keep. Returns how many servers answered.
     */
    public function sample(): int
    {
        $now = CarbonImmutable::now();
        $answered = 0;
        $rows = [];

        Server::query()
            ->whereIn('egg_id', $this->addon->eggIds())
            ->whereNull('status')
            ->with(['allocation', 'node'])
            ->each(function (Server $server) use ($now, &$answered, &$rows) {
                $status = $this->status($server);
                // Hydra's MOTD addon answers for servers that do not run, with protocol -1.
                if ($status !== null && $status['protocol'] === -1) {
                    $status = null;
                }
                $answered += $status !== null ? 1 : 0;
                $rows[] = [
                    'server_id' => $server->id,
                    'online' => $status ? min($status['online'], 65535) : null,
                    'max' => $status ? min($status['max'], 65535) : null,
                    'sampled_at' => $now,
                ];
            });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table(self::TABLE)->insert($chunk);
        }
        DB::table(self::TABLE)->where('sampled_at', '<', $now->subDays($this->addon->keepDays()))->delete();

        return $answered;
    }

    /**
     * The course of the players in the range ("day", "week" or "month"): one
     * point per bar with the most players seen in it, null where the server
     * did not answer.
     */
    public function history(Server $server, string $range): array
    {
        [$length, $width] = self::RANGES[$range] ?? self::RANGES['day'];
        $now = CarbonImmutable::now();
        $end = intdiv($now->getTimestamp(), $width) * $width + $width;
        $start = $end - $length;

        $samples = DB::table(self::TABLE)
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', CarbonImmutable::createFromTimestamp($start))
            ->orderBy('sampled_at')
            ->get(['online', 'max', 'sampled_at']);

        $buckets = [];
        $sum = 0;
        $counted = 0;
        $peak = null;
        foreach ($samples as $sample) {
            $time = CarbonImmutable::parse($sample->sampled_at)->getTimestamp();
            $bucket = intdiv($time - $start, $width);
            if (is_null($sample->online)) {
                $buckets[$bucket] ??= null;
                continue;
            }
            $online = (int) $sample->online;
            $buckets[$bucket] = max($buckets[$bucket] ?? 0, $online);
            $sum += $online;
            ++$counted;
            $peak = max($peak ?? 0, $online);
        }

        $points = [];
        for ($i = 0; $i < intdiv($length, $width); ++$i) {
            $points[] = [
                't' => CarbonImmutable::createFromTimestamp($start + $i * $width)->toAtomString(),
                'online' => $buckets[$i] ?? null,
            ];
        }

        $latest = DB::table(self::TABLE)->where('server_id', $server->id)->orderByDesc('sampled_at')->first();
        $current = $latest && !is_null($latest->online) && CarbonImmutable::parse($latest->sampled_at)->gt($now->subMinutes(15))
            ? ['online' => (int) $latest->online, 'max' => (int) $latest->max]
            : null;

        return [
            'range' => array_search([$length, $width], self::RANGES, true) ?: 'day',
            'width' => $width,
            'points' => $points,
            'current' => $current,
            'peak' => $peak,
            'average' => $counted > 0 ? round($sum / $counted, 1) : null,
        ];
    }

    private function status(Server $server): ?array
    {
        $allocation = $server->allocation;
        if (!$allocation) {
            return null;
        }

        // Servers listening on every address are reached through the node.
        $host = $allocation->ip_alias ?: $allocation->ip;
        if (in_array($host, ['0.0.0.0', '::', '[::]'], true)) {
            $host = $server->node->fqdn;
        }

        return $this->ping->status($host, (int) $allocation->port);
    }
}
