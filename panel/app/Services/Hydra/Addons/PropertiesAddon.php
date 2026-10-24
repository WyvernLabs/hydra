<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Services\Hydra\ServerNavigation;

/**
 * Hydra: a server page that shows a .properties file (e.g. server.properties
 * of Minecraft) as a form, with switches for true/false. Which eggs get the
 * page is stored on its button of the server navigation, so the addon page
 * and Design > Navigation show the same.
 */
class PropertiesAddon extends Addon
{
    public const BUTTON = 'properties';

    public function key(): string
    {
        return 'properties';
    }

    public function icon(): string
    {
        return 'sliders-horizontal';
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:191', 'regex:/^[^\\\\\0]+\.properties$/i'],
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer|exists:eggs,id',
        ];
    }

    /**
     * The eggs are no setting of the addon but of its navigation button.
     */
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
            trans('admin/addons.properties.summary_path', ['path' => $this->path()]),
            is_null($eggs)
                ? trans('admin/addons.properties.summary_all_eggs')
                : trans('admin/addons.properties.summary_eggs', ['count' => count($eggs)]),
        ];
    }

    /**
     * File inside the server, e.g. "server.properties".
     */
    public function path(): string
    {
        return ltrim((string) $this->get('path', 'server.properties'), '/') ?: 'server.properties';
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
     * Data for the React frontend (window.SiteConfiguration.properties).
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled(), 'path' => $this->path()];
    }
}
