<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Egg;
use Illuminate\Support\Collection;

/**
 * Hydra: game library (egg changer). Users switch their server to another
 * egg with one click on the page "Games"; the old files can be kept as a
 * profile to switch back to. The admin picks which eggs are offered.
 */
class GamesAddon extends Addon
{
    public const BUTTON = 'games';

    public function key(): string
    {
        return 'games';
    }

    public function icon(): string
    {
        return 'gamepad-2';
    }

    public function rules(): array
    {
        return [
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer',
            'max_profiles' => 'required|integer|min:0|max:20',
        ];
    }

    public function prepare(array $values): array
    {
        $values['eggs'] = array_values(array_unique(array_map('intval', $values['eggs'] ?? [])));

        return $values;
    }

    public function summary(): array
    {
        $eggs = $this->eggIds();

        return [
            empty($eggs) ? trans('admin/addons.games.summary_all') : trans('admin/addons.games.summary_eggs', ['count' => count($eggs)]),
            trans('admin/addons.games.summary_profiles', ['count' => $this->maxProfiles()]),
        ];
    }

    /**
     * Ids of the offered eggs; empty offers all.
     *
     * @return int[]
     */
    public function eggIds(): array
    {
        $eggs = $this->get('eggs', []);
        if (is_string($eggs)) {
            $eggs = json_decode($eggs, true);
        }

        return is_array($eggs) ? array_map('intval', $eggs) : [];
    }

    /**
     * @return Collection<int, Egg>
     */
    public function eggs(): Collection
    {
        $ids = $this->eggIds();

        return Egg::query()
            ->with('nest')
            ->when(!empty($ids), fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }

    public function offers(Egg $egg): bool
    {
        $ids = $this->eggIds();

        return empty($ids) || in_array($egg->id, $ids, true);
    }

    /**
     * Profiles a server may keep.
     */
    public function maxProfiles(): int
    {
        return (int) $this->get('max_profiles', 3);
    }
}
