<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Hydra\ServerNavigation;

/**
 * Hydra: plugin manager for Minecraft servers. Users search Modrinth (always),
 * Hangar and SpigotMC (both optional), install, update, switch off and delete
 * plugins. The page only shows on servers of the chosen eggs (button of the
 * server navigation) that have a "plugins" folder.
 */
class PluginsAddon extends Addon
{
    public const BUTTON = 'plugins';

    public function key(): string
    {
        return 'plugins';
    }

    public function icon(): string
    {
        return 'blocks';
    }

    public function rules(): array
    {
        return [
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer|exists:eggs,id',
        ];
    }

    public function booleans(): array
    {
        return ['hangar', 'spigot', 'hide_without_folder'];
    }

    public function prepare(array $values): array
    {
        $eggs = array_values(array_unique(array_map('intval', $values['eggs'] ?? [])));
        app(ServerNavigation::class)->setButtonEggs(self::BUTTON, empty($eggs) ? null : $eggs);
        unset($values['eggs']);

        return $values;
    }

    public function summary(): array
    {
        $eggs = $this->eggs();
        $sources = array_merge(['Modrinth'], $this->get('hangar', true) ? ['Hangar'] : [], $this->get('spigot', true) ? ['SpigotMC'] : []);

        return [
            implode(', ', $sources),
            is_null($eggs)
                ? trans('admin/addons.plugins.summary_all_eggs')
                : trans('admin/addons.plugins.summary_eggs', ['count' => count($eggs)]),
        ];
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
     * Whether the addon is on and offered for the egg of the server. The
     * plugins folder is checked separately (PluginManagerService::folder).
     */
    public function offersFor(Server $server): bool
    {
        $eggs = $this->eggs();

        return $this->enabled() && (is_null($eggs) || in_array($server->egg_id, $eggs, true));
    }
}
