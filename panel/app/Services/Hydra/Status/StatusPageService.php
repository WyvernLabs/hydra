<?php

namespace Pterodactyl\Services\Hydra\Status;

use Pterodactyl\Models\Node;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\HydraStatusService;
use Pterodactyl\Services\Hydra\Addons\StatusAddon;

/**
 * Hydra: the data of the public status page. It only contains names and results,
 * never addresses of nodes or applications.
 */
class StatusPageService
{
    // One bar per hour for 7 days.
    public const HOURS = 168;

    public const STATE_ONLINE = 'online';
    public const STATE_PARTIAL = 'partial';
    public const STATE_OFFLINE = 'offline';
    public const STATE_UNKNOWN = 'unknown';

    public function __construct(private StatusAddon $addon)
    {
    }

    /**
     * @return array{nodes: array, services: array, overall: string}
     */
    public function page(): array
    {
        $start = now()->startOfHour()->subHours(self::HOURS - 1);
        $rows = DB::table('hydra_status_checks')->where('hour', '>=', $start->format('Y-m-d H:i:s'))->get()->groupBy('target');

        $nodes = Node::query()
            ->when(!$this->addon->showsPrivateNodes(), fn ($query) => $query->where('public', true))
            ->orderBy('name')
            ->get()
            ->map(fn (Node $node) => $this->entry($node->name, $rows->get("node:$node->id", collect()), $start))
            ->all();

        $services = HydraStatusService::query()->where('enabled', true)->orderBy('sort')->orderBy('name')->get()
            ->map(fn (HydraStatusService $service) => $this->entry($service->name, $rows->get($service->statusKey(), collect()), $start))
            ->all();

        return ['nodes' => $nodes, 'services' => $services, 'overall' => $this->overall(array_merge($nodes, $services))];
    }

    private function entry(string $name, Collection $rows, mixed $start): array
    {
        $byHour = $rows->keyBy(fn ($row) => substr((string) $row->hour, 0, 13));
        $bars = [];
        for ($i = 0; $i < self::HOURS; ++$i) {
            $hour = $start->copy()->addHours($i);
            $row = $byHour->get($hour->format('Y-m-d H'));
            $bars[] = [
                'hour' => $hour,
                'state' => $row ? $this->hourState((int) $row->checks, (int) $row->failures) : self::STATE_UNKNOWN,
                'uptime' => $row ? $this->percent((int) $row->checks, (int) $row->failures) : null,
            ];
        }

        $checks = (int) $rows->sum('checks');
        // The last result only counts when it is recent; otherwise the checks stopped running.
        $latest = $rows->sortByDesc('hour')->first();
        $recent = $latest && (string) $latest->hour >= now()->startOfHour()->subHour()->format('Y-m-d H:i:s');

        return [
            'name' => $name,
            'state' => $recent ? ($latest->online ? self::STATE_ONLINE : self::STATE_OFFLINE) : self::STATE_UNKNOWN,
            'uptime' => $checks > 0 ? $this->percent($checks, (int) $rows->sum('failures')) : null,
            'bars' => $bars,
        ];
    }

    /**
     * Green: always online. Red: offline for the whole hour. Yellow: offline for a while.
     */
    public function hourState(int $checks, int $failures): string
    {
        if ($checks === 0) {
            return self::STATE_UNKNOWN;
        }
        if ($failures === 0) {
            return self::STATE_ONLINE;
        }

        return $failures >= $checks ? self::STATE_OFFLINE : self::STATE_PARTIAL;
    }

    private function percent(int $checks, int $failures): float
    {
        return round(($checks - $failures) / $checks * 100, 2);
    }

    private function overall(array $entries): string
    {
        $states = array_column($entries, 'state');
        if (in_array(self::STATE_OFFLINE, $states, true)) {
            return self::STATE_OFFLINE;
        }
        if (empty($states) || in_array(self::STATE_UNKNOWN, $states, true)) {
            return self::STATE_UNKNOWN;
        }

        return self::STATE_ONLINE;
    }
}
