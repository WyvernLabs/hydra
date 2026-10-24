<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Illuminate\Support\Str;
use Matriphe\ISO639\ISO639;
use Illuminate\Validation\Rule;

/**
 * Hydra: support tickets. Users write them from their account, the team
 * answers in the admin area under Community > Tickets. Messages are Markdown
 * and may show uploaded images.
 *
 * The admin picks the languages support is offered in (any language, not only
 * those of the panel) and names every category in each of them. Users pick
 * the language of a ticket and see the categories in it.
 */
class TicketsAddon extends Addon
{
    public function key(): string
    {
        return 'tickets';
    }

    public function icon(): string
    {
        return 'life-buoy';
    }

    public function rules(): array
    {
        return [
            'languages' => 'required|array|min:1|max:30',
            'languages.*' => ['required', 'string', Rule::in(array_keys(self::allLanguages()))],
            'categories' => 'nullable|array|max:50',
            'categories.*.id' => 'nullable|string|max:32|alpha_dash',
            'categories.*.names' => 'nullable|array',
            'categories.*.names.*' => 'nullable|string|max:64',
            'upload_limit' => 'required|integer|between:1,50',
            'max_open' => 'required|integer|between:0,100',
        ];
    }

    public function booleans(): array
    {
        return ['notify'];
    }

    /**
     * Keeps the names of the chosen languages only, drops categories without
     * any name and gives new ones an ID, which tickets store.
     */
    public function prepare(array $values): array
    {
        $languages = array_values(array_unique($values['languages'] ?? []));
        $values['languages'] = $languages;

        $categories = [];
        foreach ($values['categories'] ?? [] as $category) {
            $names = array_filter(
                array_map(fn ($name) => trim((string) $name), array_intersect_key($category['names'] ?? [], array_flip($languages))),
                fn (string $name) => $name !== ''
            );
            if (empty($names)) {
                continue;
            }

            $categories[] = ['id' => ($category['id'] ?? null) ?: Str::lower(Str::random(8)), 'names' => $names];
        }
        $values['categories'] = $categories;

        return $values;
    }

    public function summary(): array
    {
        $names = array_map(fn (array $category) => $this->categoryName($category['id'], app()->getLocale()), $this->categories());

        return [
            trans('admin/addons.tickets.summary_languages', ['languages' => implode(', ', array_map(fn ($code) => self::languageName($code), $this->languages()))]),
            count($names) > 0
                ? trans('admin/addons.tickets.summary_categories', ['categories' => implode(', ', $names)])
                : trans('admin/addons.tickets.summary_no_categories'),
            trans('admin/addons.tickets.summary_upload', ['size' => $this->uploadLimit()]),
        ];
    }

    /**
     * Codes of the languages support is offered in, e.g. ["de", "en"].
     *
     * @return string[]
     */
    public function languages(): array
    {
        $languages = $this->decode($this->get('languages'));

        return !empty($languages) ? array_values($languages) : [config('app.locale', 'en')];
    }

    /**
     * Every category with its names by language.
     *
     * @return array<int, array{id: string, names: array<string, string>}>
     */
    public function categories(): array
    {
        $stored = $this->get('categories');
        $categories = $this->decode($stored);

        // Before languages existed the categories were one name per line.
        if (is_null($categories) && is_string($stored) && trim($stored) !== '') {
            $language = $this->languages()[0];

            return collect(preg_split('/\r\n|\r|\n/', $stored))
                ->map(fn ($line) => mb_substr(trim($line), 0, 64))
                ->filter()
                ->unique()
                ->map(fn ($name) => ['id' => $name, 'names' => [$language => $name]])
                ->values()
                ->all();
        }

        return array_values(array_filter($categories ?? [], fn ($category) => is_array($category) && isset($category['id'])));
    }

    public function hasCategory(?string $id): bool
    {
        return in_array($id, array_column($this->categories(), 'id'), true);
    }

    /**
     * Name of a category in the language, else in the first support language,
     * else in any. Categories that do not exist anymore keep their stored value.
     */
    public function categoryName(?string $id, ?string $language): ?string
    {
        if (blank($id)) {
            return null;
        }

        foreach ($this->categories() as $category) {
            if ($category['id'] === $id) {
                $names = $category['names'] ?? [];

                return $names[$language] ?? $names[$this->languages()[0]] ?? (reset($names) ?: $id);
            }
        }

        return $id;
    }

    /**
     * Native name of a language, e.g. "Deutsch" for "de".
     */
    public static function languageName(?string $code): string
    {
        return self::allLanguages()[$code] ?? (string) $code;
    }

    /**
     * Every language by its ISO 639-1 code with its native name.
     *
     * @return array<string, string>
     */
    public static function allLanguages(): array
    {
        static $languages = null;

        return $languages ??= collect(app(ISO639::class)->allLanguages())
            ->filter(fn (array $language) => !empty($language[0]))
            ->mapWithKeys(fn (array $language) => [$language[0] => Str::ucfirst(explode(',', $language[5] ?: $language[4])[0])])
            ->sort()
            ->all();
    }

    /**
     * Largest image in megabytes.
     */
    public function uploadLimit(): int
    {
        return (int) $this->get('upload_limit', 5);
    }

    /**
     * Unclosed tickets a user may have at once, 0 for any number.
     */
    public function maxOpen(): int
    {
        return (int) $this->get('max_open', 5);
    }

    /**
     * Users get an email when the team answers.
     */
    public function notifies(): bool
    {
        return (bool) $this->get('notify', true);
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.tickets).
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'languages' => array_map(fn ($code) => ['code' => $code, 'name' => self::languageName($code)], $this->languages()),
            'categories' => $this->categories(),
            'uploadLimit' => $this->uploadLimit(),
        ];
    }

    /**
     * Lists are stored as JSON, but are arrays right after saving.
     */
    private function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : null;
    }
}
