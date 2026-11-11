<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: category of the knowledgebase (addon "knowledgebase"). Categories
 * without a parent are main categories, the others their subcategories;
 * there is only this one level.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property array $names names per language
 * @property int $sort
 * @property HydraKbCategory|null $parent
 * @property \Illuminate\Database\Eloquent\Collection<int, HydraKbCategory> $children
 * @property \Illuminate\Database\Eloquent\Collection<int, HydraKbPost> $posts
 */
class HydraKbCategory extends Model
{
    public const RESOURCE_NAME = 'hydra_kb_category';

    protected $table = 'hydra_kb_categories';

    protected bool $immutableDates = true;

    protected $fillable = ['parent_id', 'names', 'sort'];

    protected $casts = [
        'parent_id' => 'integer',
        'names' => 'array',
        'sort' => 'integer',
    ];

    public static array $validationRules = [
        'parent_id' => 'nullable|integer',
        'names' => 'required|array',
        'sort' => 'integer|min:0',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(HydraKbPost::class, 'category_id')->orderBy('sort')->orderBy('id');
    }

    public function name(?string $locale = null): string
    {
        return HydraKbPost::pick($this->names, $locale);
    }
}
