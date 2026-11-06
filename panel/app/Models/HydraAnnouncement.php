<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Hydra: an announcement in the client area, see Design > Announcements.
 *
 * @property int $id
 * @property string $type
 * @property array $title texts per language
 * @property array $description texts per language
 * @property array|null $button_text texts per language
 * @property string|null $button_url
 * @property int[]|null $nodes null = general
 * @property bool $dismissible users may hide it for DISMISS_HOURS
 * @property bool $enabled
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property int $sort
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class HydraAnnouncement extends Model
{
    public const RESOURCE_NAME = 'hydra_announcement';

    /**
     * How long an announcement stays hidden for a user who closed it.
     */
    public const DISMISS_HOURS = 24;

    /**
     * Types with the icon of each, colors are in public/hydra/layout.css.
     */
    public const TYPES = [
        'info' => 'info',
        'success' => 'circle-check',
        'warning' => 'triangle-alert',
        'critical' => 'octagon-x',
        'maintenance' => 'wrench',
    ];

    protected $table = 'hydra_announcements';

    protected bool $immutableDates = true;

    protected $fillable = ['type', 'title', 'description', 'button_text', 'button_url', 'nodes', 'dismissible', 'enabled', 'expires_at', 'sort'];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'button_text' => 'array',
        'nodes' => 'array',
        'dismissible' => 'boolean',
        'enabled' => 'boolean',
        'expires_at' => 'datetime',
        'sort' => 'integer',
    ];

    public static array $validationRules = [
        'type' => 'required|string|in:info,success,warning,critical,maintenance',
        'title' => 'required',
        'description' => 'required',
        'button_text' => 'nullable',
        'button_url' => 'nullable|string|max:2048',
        'nodes' => 'nullable',
        'dismissible' => 'boolean',
        'enabled' => 'boolean',
        'expires_at' => 'nullable|date',
        'sort' => 'integer|min:0',
    ];

    /**
     * @param Builder<HydraAnnouncement> $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('enabled', true)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('sort')
            ->orderByDesc('id');
    }

    public function isExpired(): bool
    {
        return !is_null($this->expires_at) && $this->expires_at->isPast();
    }

    public function isGeneral(): bool
    {
        return empty($this->nodes);
    }

    /**
     * Whether it goes to users of servers on one of these nodes.
     *
     * @param int[] $nodes
     */
    public function isForNodes(array $nodes): bool
    {
        return !empty(array_intersect(array_map('intval', $this->nodes ?? []), $nodes));
    }

    /**
     * The value in the current language, falling back to the panel language
     * and then to any value there is.
     */
    public function localized(?array $values): string
    {
        $values ??= [];
        foreach ([app()->getLocale(), config('app.locale'), 'en'] as $key) {
            if (!blank($values[$key] ?? null)) {
                return $values[$key];
            }
        }

        return (string) (collect($values)->first(fn ($value) => !blank($value)) ?? '');
    }

    /**
     * Data for the client area.
     */
    public function toClientArray(): array
    {
        $text = $this->localized($this->button_text);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->localized($this->title),
            'description' => $this->localized($this->description),
            'button' => $this->button_url && $text !== '' ? ['text' => $text, 'url' => $this->button_url] : null,
            'dismissible' => $this->dismissible,
        ];
    }
}
