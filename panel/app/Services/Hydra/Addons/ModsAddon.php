<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Services\Hydra\Minecraft\Mods\CurseForgeSource;

/**
 * Hydra: mod manager for modded Minecraft servers (Fabric, Forge, NeoForge,
 * Quilt). Users search Modrinth (always) and CurseForge (with an API key of
 * the admin), filter by category, install mods with their required
 * dependencies, update, switch off and delete them. The page only shows on
 * servers of the chosen eggs (button of the server navigation) that have a
 * "mods" folder.
 */
class ModsAddon extends Addon
{
    public const BUTTON = 'mods';

    // Cache key of the answer CurseForge gave to the last new API key.
    public const KEY_CHECK = 'hydra:mods:curseforge:key_check';

    public function key(): string
    {
        return 'mods';
    }

    public function icon(): string
    {
        return 'puzzle';
    }

    public function rules(): array
    {
        return [
            'curseforge_key' => 'nullable|string|max:191',
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer|exists:eggs,id',
        ];
    }

    public function encrypted(): array
    {
        return ['curseforge_key'];
    }

    public function booleans(): array
    {
        return ['curseforge', 'dependencies', 'hide_without_folder'];
    }

    public function prepare(array $values): array
    {
        // A new key is tried once and always stored; the settings show what
        // CurseForge answered, so a typo shows up right away.
        $key = preg_replace('/\s+/', '', (string) ($values['curseforge_key'] ?? '')) ?? '';
        $values['curseforge_key'] = $key;
        if ($key !== '') {
            Cache::forever(self::KEY_CHECK, array_merge(CurseForgeSource::checkKey($key), ['at' => now()->toIso8601String()]));
        }

        $eggs = array_values(array_unique(array_map('intval', $values['eggs'] ?? [])));
        app(ServerNavigation::class)->setButtonEggs(self::BUTTON, empty($eggs) ? null : $eggs);
        unset($values['eggs']);

        return $values;
    }

    public function summary(): array
    {
        $eggs = $this->eggs();
        $sources = ['Modrinth'];
        if ($this->get('curseforge', false)) {
            $sources[] = $this->hasCurseForge() ? 'CurseForge' : trans('admin/addons.mods.summary_no_key');
        }

        return [
            implode(', ', $sources),
            is_null($eggs)
                ? trans('admin/addons.mods.summary_all_eggs')
                : trans('admin/addons.mods.summary_eggs', ['count' => count($eggs)]),
        ];
    }

    /**
     * What CurseForge answered to the last new key: HTTP status of a request
     * that needs a valid key and of the mod search (0 = unreachable), null
     * when no key was checked yet.
     *
     * @return array{status: int, search: int}|null
     */
    public function keyStatus(): ?array
    {
        $check = Cache::get(self::KEY_CHECK);

        return is_array($check) && isset($check['status'], $check['search'])
            ? ['status' => (int) $check['status'], 'search' => (int) $check['search']]
            : null;
    }

    /**
     * Whether CurseForge is switched on and has a key.
     */
    public function hasCurseForge(): bool
    {
        return (bool) $this->get('curseforge', false) && !empty($this->get('curseforge_key'));
    }

    /**
     * Whether the page and its button disappear on servers without the
     * folder (otherwise the page explains what is missing).
     */
    public function hidesWithoutFolder(): bool
    {
        return (bool) $this->get('hide_without_folder', true);
    }

    /**
     * Eggs whose servers get the page, null for every egg.
     *
     * @return int[]|null
     */
    public function eggs(): ?array
    {
        return app(ServerNavigation::class)->buttonEggs(self::BUTTON);
    }

    /**
     * Whether the addon is on and offered for the egg of the server. The mods
     * folder is checked separately (ModManagerService::folder).
     */
    public function offersFor(Server $server): bool
    {
        $eggs = $this->eggs();

        return $this->enabled() && (is_null($eggs) || in_array($server->egg_id, $eggs, true));
    }
}
