<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Hydra: a link in the sidebar of the client area, leading either to an own
 * URL or to a page written in Markdown.
 *
 * Title and content are stored per language ({"en": "…", "de": "…"}).
 *
 * @property int $id
 * @property string $type
 * @property array $title
 * @property string $icon
 * @property string|null $slug
 * @property string|null $url
 * @property array|null $content
 * @property bool $enabled
 * @property bool $public
 * @property int $sort
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PanelLink extends Model
{
    public const RESOURCE_NAME = 'panel_link';

    public const TYPE_PAGE = 'page';
    public const TYPE_URL = 'url';

    /**
     * Icons an admin can pick for a link.
     */
    public const ICONS = [
        'document-text', 'identification', 'scale', 'shield-check', 'information-circle',
        'question-mark-circle', 'book-open', 'chat', 'globe-alt', 'link', 'heart', 'discord',
    ];

    protected $table = 'panel_links';

    protected $fillable = ['type', 'title', 'icon', 'slug', 'url', 'content', 'enabled', 'public', 'sort'];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'enabled' => 'boolean',
        'public' => 'boolean',
        'sort' => 'integer',
    ];

    public static array $validationRules = [
        'type' => 'required|string|in:page,url',
        'title' => 'required|array',
        'icon' => 'required|string|max:32',
        'slug' => 'nullable|required_if:type,page|string|max:64|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|unique:panel_links,slug',
        'url' => 'nullable|string|max:2048|url:http,https',
        'content' => 'nullable|array',
        'enabled' => 'boolean',
        'public' => 'boolean',
        'sort' => 'integer|min:0',
    ];

    /**
     * Links shown in the sidebar: enabled ones, and URL links only once they
     * have a URL.
     *
     * @param Builder<PanelLink> $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('enabled', true)
            ->where(fn (Builder $query) => $query->where('type', self::TYPE_PAGE)->orWhereNotNull('url'))
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * The title in the current language, falling back to the panel language
     * and then to any title there is.
     */
    public function localizedTitle(?string $locale = null): string
    {
        return $this->localized($this->title, $locale);
    }

    /**
     * The page content (Markdown) in the current language, with the same fallbacks.
     */
    public function localizedContent(?string $locale = null): string
    {
        return $this->localized($this->content ?? [], $locale);
    }

    /**
     * Where the link leads to.
     */
    public function href(): string
    {
        return $this->type === self::TYPE_URL ? (string) $this->url : '/pages/' . $this->slug;
    }

    /**
     * Data for the sidebar of the React client area.
     */
    public function toSidebarArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->localizedTitle(),
            'icon' => $this->icon,
            'slug' => $this->slug,
            'url' => $this->type === self::TYPE_URL ? $this->url : null,
        ];
    }

    private function localized(array $values, ?string $locale): string
    {
        $locale ??= app()->getLocale();

        foreach ([$locale, config('app.locale'), 'en'] as $key) {
            if (!blank($values[$key] ?? null)) {
                return $values[$key];
            }
        }

        return (string) (collect($values)->first(fn ($value) => !blank($value)) ?? '');
    }
}
