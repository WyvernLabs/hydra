<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Hydra\ServerNavigation;

/**
 * Hydra: Minecraft version changer. Users pick a server software, a version
 * and a build from mcjars.app and the panel installs it on the server. Which
 * eggs get the page is stored on its button of the server navigation.
 */
class VersionsAddon extends Addon
{
    public const BUTTON = 'versions';

    public function key(): string
    {
        return 'versions';
    }

    public function icon(): string
    {
        return 'package';
    }

    public function rules(): array
    {
        return [
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer|exists:eggs,id',
        ];
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

        return [
            is_null($eggs)
                ? trans('admin/addons.versions.summary_all_eggs')
                : trans('admin/addons.versions.summary_eggs', ['count' => count($eggs)]),
        ];
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
     * Whether the addon is on and offered for the egg of the server.
     */
    public function offersFor(Server $server): bool
    {
        $eggs = $this->eggs();

        return $this->enabled() && (is_null($eggs) || in_array($server->egg_id, $eggs, true));
    }
}
