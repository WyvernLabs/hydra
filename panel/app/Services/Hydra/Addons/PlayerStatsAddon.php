<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;

/**
 * Hydra: statistics of the players online on Minecraft Java servers. The
 * panel asks the servers of the chosen eggs every five minutes with the
 * server list ping; their dashboard shows the course over 24 hours, 7 and 30 days.
 */
class PlayerStatsAddon extends Addon
{
    public function key(): string
    {
        return 'player_stats';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function rules(): array
    {
        return [
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer|exists:eggs,id',
            'keep_days' => 'required|integer|between:1,90',
        ];
    }

    public function prepare(array $values): array
    {
        $values['eggs'] = array_values(array_unique(array_map('intval', $values['eggs'] ?? [])));

        return $values;
    }

    public function missing(): ?string
    {
        return empty($this->eggIds()) ? trans('admin/addons.player_stats.no_eggs') : null;
    }

    public function summary(): array
    {
        return [
            trans('admin/addons.player_stats.summary', ['count' => count($this->eggIds()), 'days' => $this->keepDays()]),
        ];
    }

    /**
     * @return int[]
     */
    public function eggIds(): array
    {
        $eggs = $this->get('eggs', []);
        if (is_string($eggs)) {
            $eggs = json_decode($eggs, true);
        }

        return is_array($eggs) ? array_values(array_map('intval', $eggs)) : [];
    }

    public function keepDays(): int
    {
        return (int) $this->get('keep_days', 30);
    }

    /**
     * Whether the players of the server are counted.
     */
    public function tracks(Server $server): bool
    {
        return $this->enabled() && in_array($server->egg_id, $this->eggIds(), true);
    }
}
