<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Models\Egg;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: the navigation of the server pages (Design > Navigation).
 *
 * Buttons are grouped into categories. Every category has a name per language
 * and an optional color for the icons of its buttons; every button has a
 * Lucide icon and can be limited to some eggs. Stored as JSON in the setting
 * "settings::hydra:navigation:server":
 *
 *   {"categories": [{"id": "server", "name": {"en": "…"}, "color": "#74c236",
 *     "items": [{"key": "console", "icon": "square-terminal", "eggs": null}, …]}, …]}
 */
class ServerNavigation
{
    use AvailableLanguages;

    public const SETTING = 'settings::hydra:navigation:server';

    /**
     * Every button of the server pages with its default icon, in the default order.
     */
    public const BUTTONS = [
        'console' => 'square-terminal',
        'files' => 'folder',
        'databases' => 'database',
        'schedules' => 'calendar-clock',
        'users' => 'users',
        'backups' => 'archive',
        'network' => 'network',
        'startup' => 'play',
        'settings' => 'settings',
        'activity' => 'rotate-ccw-clock',
    ];

    public const MAX_CATEGORIES = 20;

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public static function defaults(): array
    {
        return [
            'categories' => [[
                'id' => 'server',
                'name' => [],
                'color' => null,
                'items' => collect(self::BUTTONS)
                    ->map(fn (string $icon, string $key) => ['key' => $key, 'icon' => $icon, 'eggs' => null])
                    ->values()
                    ->all(),
            ]],
        ];
    }

    /**
     * The current navigation. Broken or outdated data is repaired on the fly,
     * e.g. buttons added by an update land in the first category.
     */
    public function get(): array
    {
        $raw = json_decode((string) config('hydra.navigation.server'), true);

        return is_array($raw) ? $this->repair($raw) : self::defaults();
    }

    /**
     * Validates and stores a navigation sent by the editor.
     *
     * @throws ValidationException
     */
    public function save(array $navigation): array
    {
        $navigation = $this->validate($navigation);

        $this->settings->set(self::SETTING, json_encode($navigation));
        config()->set('hydra.navigation.server', json_encode($navigation));

        return $navigation;
    }

    public function reset(): void
    {
        $this->settings->forget(self::SETTING);
        config()->set('hydra.navigation.server', null);
    }

    /**
     * Data for the React frontend: names resolved for the current language.
     */
    public function toFrontend(): array
    {
        $locale = app()->getLocale();
        $fallback = config('app.locale', 'en');

        return array_map(fn (array $category) => [
            'id' => $category['id'],
            'name' => ($category['name'][$locale] ?? null) ?: (($category['name'][$fallback] ?? null) ?: (array_values(array_filter($category['name']))[0] ?? null)),
            'color' => $category['color'],
            'items' => $category['items'],
        ], $this->get()['categories']);
    }

    /**
     * Every icon the navigation uses.
     *
     * @return string[]
     */
    public function icons(): array
    {
        return collect($this->get()['categories'])->pluck('items')->flatten(1)->pluck('icon')->unique()->values()->all();
    }

    /**
     * @throws ValidationException
     */
    private function validate(array $navigation): array
    {
        $categories = $navigation['categories'] ?? null;
        if (!is_array($categories) || count($categories) < 1) {
            throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.no_category')]);
        }
        if (count($categories) > self::MAX_CATEGORIES) {
            throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.too_many', ['max' => self::MAX_CATEGORIES])]);
        }

        $eggs = Egg::query()->pluck('id')->all();
        $seen = [];
        foreach ($categories as $category) {
            if (!is_array($category) || !is_array($category['items'] ?? [])) {
                throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.invalid')]);
            }
            if (!is_null($category['color'] ?? null) && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $category['color'])) {
                throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.color')]);
            }
            foreach ((array) ($category['name'] ?? []) as $name) {
                if (!is_null($name) && (!is_string($name) || mb_strlen($name) > 64)) {
                    throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.name')]);
                }
            }
            foreach ($category['items'] ?? [] as $item) {
                $key = $item['key'] ?? null;
                if (!is_string($key) || !array_key_exists($key, self::BUTTONS) || in_array($key, $seen, true)) {
                    throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.invalid')]);
                }
                $seen[] = $key;
                if (!is_string($item['icon'] ?? null) || !Icons::exists($item['icon'])) {
                    throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.icon', ['icon' => (string) ($item['icon'] ?? '')])]);
                }
                $itemEggs = $item['eggs'] ?? null;
                if (!is_null($itemEggs) && (!is_array($itemEggs) || array_diff(array_map('intval', $itemEggs), $eggs))) {
                    throw ValidationException::withMessages(['navigation' => trans('admin/design.navigation.errors.eggs')]);
                }
            }
        }

        return $this->repair($navigation);
    }

    /**
     * Brings stored or submitted data into the expected shape: valid ids,
     * names only for installed languages, every button exactly once.
     */
    private function repair(array $navigation): array
    {
        $languages = array_keys($this->getAvailableLanguages());
        $seen = [];
        $ids = [];
        $categories = [];

        foreach (array_slice((array) ($navigation['categories'] ?? []), 0, self::MAX_CATEGORIES) as $category) {
            if (!is_array($category)) {
                continue;
            }

            $id = Str::slug((string) ($category['id'] ?? '')) ?: Str::lower(Str::random(8));
            while (in_array($id, $ids, true)) {
                $id .= '-' . Str::lower(Str::random(3));
            }
            $ids[] = $id = Str::limit($id, 32, '');

            $items = [];
            foreach ((array) ($category['items'] ?? []) as $item) {
                $key = is_array($item) ? ($item['key'] ?? null) : null;
                if (!is_string($key) || !array_key_exists($key, self::BUTTONS) || in_array($key, $seen, true)) {
                    continue;
                }
                $seen[] = $key;
                $icon = is_string($item['icon'] ?? null) && Icons::exists($item['icon']) ? $item['icon'] : self::BUTTONS[$key];
                $eggs = is_array($item['eggs'] ?? null) ? array_values(array_unique(array_map('intval', $item['eggs']))) : null;
                $items[] = ['key' => $key, 'icon' => $icon, 'eggs' => $eggs];
            }

            $names = [];
            foreach ($languages as $code) {
                $name = trim((string) (((array) ($category['name'] ?? []))[$code] ?? ''));
                if ($name !== '') {
                    $names[$code] = mb_substr($name, 0, 64);
                }
            }

            $color = $category['color'] ?? null;
            $categories[] = [
                'id' => $id,
                'name' => $names,
                'color' => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : null,
                'items' => $items,
            ];
        }

        if (empty($categories)) {
            return self::defaults();
        }

        // Buttons that are missing (e.g. new in an update) go to the first category.
        foreach (self::BUTTONS as $key => $icon) {
            if (!in_array($key, $seen, true)) {
                $categories[0]['items'][] = ['key' => $key, 'icon' => $icon, 'eggs' => null];
            }
        }

        return ['categories' => $categories];
    }
}
