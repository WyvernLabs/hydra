<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\PwaService;
use Pterodactyl\Services\Hydra\BrandingService;

/**
 * Hydra: the panel can be installed as an app (progressive web app). In the
 * installed app, pages open in tabs inside the window instead of new windows,
 * so several servers can be used side by side.
 */
class PwaAddon extends Addon
{
    public function key(): string
    {
        return 'pwa';
    }

    public function icon(): string
    {
        return 'app-window';
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:60',
            'short_name' => 'nullable|string|max:20',
            'theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon_upload' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096|dimensions:min_width=192,min_height=192',
            'icon_remove' => 'nullable|boolean',
        ];
    }

    public function booleans(): array
    {
        return ['tabs'];
    }

    public function prepare(array $values): array
    {
        $service = app(PwaService::class);
        if (($values['icon_upload'] ?? null) instanceof UploadedFile) {
            $service->storeIcon($values['icon_upload']);
        } elseif (!empty($values['icon_remove'])) {
            $service->removeIcon();
        }
        unset($values['icon_upload'], $values['icon_remove']);

        $values['name'] = trim((string) ($values['name'] ?? ''));
        $values['short_name'] = trim((string) ($values['short_name'] ?? ''));
        $values['theme_color'] = strtolower($values['theme_color']);
        $values['background_color'] = strtolower($values['background_color']);

        return $values;
    }

    /**
     * New icons for the new colours.
     */
    public function saved(): void
    {
        app(PwaService::class)->clearIcons();
    }

    public function summary(): array
    {
        return [
            $this->appName(),
            $this->tabs() ? trans('admin/addons.pwa.summary_tabs') : trans('admin/addons.pwa.summary_no_tabs'),
        ];
    }

    public function appName(): string
    {
        return ((string) $this->get('name')) ?: (string) config('app.name', 'Hydra');
    }

    public function shortName(): string
    {
        return ((string) $this->get('short_name')) ?: mb_substr($this->appName(), 0, 12);
    }

    public function themeColor(): string
    {
        $color = (string) $this->get('theme_color');

        return BrandingService::isValidColor($color) ? $color : '#141414';
    }

    public function backgroundColor(): string
    {
        $color = (string) $this->get('background_color');

        return BrandingService::isValidColor($color) ? $color : '#121212';
    }

    /**
     * Pages open in tabs inside the installed app.
     */
    public function tabs(): bool
    {
        return (bool) $this->get('tabs', true);
    }

    public function toArray(): array
    {
        return ['enabled' => $this->enabled(), 'tabs' => $this->enabled() && $this->tabs()];
    }
}
