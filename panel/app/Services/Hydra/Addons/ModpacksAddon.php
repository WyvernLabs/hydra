<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Hydra\ServerNavigation;

/**
 * Hydra: modpack installer for Minecraft servers. Users search modpacks of
 * Modrinth (always) and CurseForge (with an API key), and one click installs
 * the pack: the server software in exactly the version the pack needs
 * (through mcjars.app, like the version changer), its configs and its mods.
 * The page shows on servers of the chosen eggs (button of the navigation).
 */
class ModpacksAddon extends Addon
{
    public const BUTTON = 'modpacks';

    public function key(): string
    {
        return 'modpacks';
    }

    public function icon(): string
    {
        return 'package-open';
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
        return ['curseforge'];
    }

    public function prepare(array $values): array
    {
        $values['curseforge_key'] = preg_replace('/\s+/', '', (string) ($values['curseforge_key'] ?? '')) ?? '';

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
            $sources[] = $this->curseForgeKey() !== '' ? 'CurseForge' : trans('admin/addons.modpacks.summary_no_key');
        }

        return [
            implode(', ', $sources),
            is_null($eggs)
                ? trans('admin/addons.modpacks.summary_all_eggs')
                : trans('admin/addons.modpacks.summary_eggs', ['count' => count($eggs)]),
        ];
    }

    /**
     * The own CurseForge key, otherwise the one of the mod manager.
     */
    public function curseForgeKey(): string
    {
        return (string) ($this->get('curseforge_key') ?: app(ModsAddon::class)->get('curseforge_key') ?: '');
    }

    public function hasCurseForge(): bool
    {
        return (bool) $this->get('curseforge', false) && $this->curseForgeKey() !== '';
    }

    /**
     * @return int[]|null eggs whose servers get the page, null for every egg
     */
    public function eggs(): ?array
    {
        return app(ServerNavigation::class)->buttonEggs(self::BUTTON);
    }

    public function offersFor(Server $server): bool
    {
        $eggs = $this->eggs();

        return $this->enabled() && (is_null($eggs) || in_array($server->egg_id, $eggs, true));
    }
}
