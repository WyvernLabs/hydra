<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: post of the knowledgebase (addon "knowledgebase"). Title and text
 * are Discord style Markdown per language; the panel's default language is
 * required, the others fall back to it.
 *
 * @property int $id
 * @property int $category_id
 * @property string $slug
 * @property array $titles
 * @property array $contents
 * @property bool $published
 * @property int $sort
 * @property int|null $author_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property HydraKbCategory $category
 * @property User|null $author
 */
class HydraKbPost extends Model
{
    public const RESOURCE_NAME = 'hydra_kb_post';

    public const MAX_TITLE = 160;

    public const MAX_CONTENT = 60000;

    protected $table = 'hydra_kb_posts';

    protected bool $immutableDates = true;

    protected $fillable = ['category_id', 'slug', 'titles', 'contents', 'published', 'sort', 'author_id'];

    protected $casts = [
        'category_id' => 'integer',
        'titles' => 'array',
        'contents' => 'array',
        'published' => 'boolean',
        'sort' => 'integer',
        'author_id' => 'integer',
    ];

    public static array $validationRules = [
        'category_id' => 'required|integer',
        'slug' => 'required|string|max:120',
        'titles' => 'required|array',
        'contents' => 'required|array',
        'published' => 'boolean',
        'sort' => 'integer|min:0',
        'author_id' => 'nullable|integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HydraKbCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function title(?string $locale = null): string
    {
        return self::pick($this->titles, $locale);
    }

    public function content(?string $locale = null): string
    {
        return self::pick($this->contents, $locale);
    }

    /**
     * The value in the given (or current) language, otherwise in the panel's
     * default language, otherwise any value there is.
     */
    public static function pick(?array $values, ?string $locale = null): string
    {
        $values ??= [];
        foreach ([$locale ?? app()->getLocale(), config('app.locale'), 'en'] as $key) {
            if (!blank($values[$key] ?? null)) {
                return (string) $values[$key];
            }
        }

        return (string) (collect($values)->first(fn ($value) => !blank($value)) ?? '');
    }
}
