<?php

namespace Pterodactyl\Services\Hydra\Support;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use Illuminate\Contracts\Session\Session;

/**
 * Hydra: the servers an admin has open in the game support area. The tabs
 * live in the session of the admin and close themselves after LIFETIME; a
 * websocket token is only handed out for an open tab.
 */
class SupportTabs
{
    public const LIFETIME_MINUTES = 240;

    public const MAX_TABS = 10;

    private const KEY = 'hydra.support.tabs';

    public function __construct(private Session $session)
    {
    }

    /**
     * Finds a server by its short ID ("1133cda4"), its full UUID or a link
     * that contains one of them.
     */
    public static function find(string $input): ?Server
    {
        $input = strtolower(trim($input));

        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', $input, $match)) {
            return Server::query()->where('uuid', $match[0])->first();
        }
        // In a link the ID follows /server/, e.g. https://panel.example/server/1133cda4/files.
        if (preg_match('#/server/([0-9a-z]{8})(?:$|[^0-9a-z])#', $input, $match)
            || preg_match('/(?:^|[^0-9a-z])([0-9a-z]{8})(?:$|[^0-9a-z])/', $input, $match)) {
            return Server::query()->where('uuidShort', $match[1])->first();
        }

        return null;
    }

    /**
     * Open tabs as server ID => time it was opened, without expired ones.
     *
     * @return array<int, CarbonImmutable>
     */
    public function all(): array
    {
        $tabs = [];
        foreach ((array) $this->session->get(self::KEY, []) as $id => $openedAt) {
            $opened = CarbonImmutable::createFromTimestamp((int) $openedAt);
            if ($opened->addMinutes(self::LIFETIME_MINUTES)->isFuture()) {
                $tabs[(int) $id] = $opened;
            }
        }

        $this->store($tabs);

        return $tabs;
    }

    /**
     * Opens a tab, or keeps the time of one that is already open. The oldest
     * tab is closed when there are too many.
     */
    public function open(Server $server): void
    {
        $tabs = $this->all();
        if (!isset($tabs[$server->id])) {
            $tabs[$server->id] = CarbonImmutable::now();
            uasort($tabs, fn ($a, $b) => $a <=> $b);
            while (count($tabs) > self::MAX_TABS) {
                array_shift($tabs);
            }
        }

        $this->store($tabs);
    }

    public function close(Server $server): void
    {
        $this->forget($server->id);
    }

    public function forget(int $id): void
    {
        $tabs = $this->all();
        unset($tabs[$id]);
        $this->store($tabs);
    }

    public function isOpen(Server $server): bool
    {
        return isset($this->all()[$server->id]);
    }

    public function expiresAt(Server $server): ?CarbonImmutable
    {
        return ($this->all()[$server->id] ?? null)?->addMinutes(self::LIFETIME_MINUTES);
    }

    /**
     * @param array<int, CarbonImmutable> $tabs
     */
    private function store(array $tabs): void
    {
        $this->session->put(self::KEY, array_map(fn (CarbonImmutable $opened) => $opened->getTimestamp(), $tabs));
    }
}
