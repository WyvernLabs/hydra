<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Hydra: card ("block") of the Kanban board of a server (addon "board").
 *
 * @property int $id
 * @property int $server_id
 * @property int $column_id
 * @property string $name
 * @property string|null $description
 * @property int $sort
 * @property int|null $claimed_by
 * @property \Carbon\CarbonImmutable|null $claimed_at
 * @property int|null $created_by
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property HydraBoardColumn $column
 * @property User|null $claimer
 * @property \Illuminate\Database\Eloquent\Collection<int, HydraBoardTag> $tags
 * @property \Illuminate\Database\Eloquent\Collection<int, HydraBoardChecklistItem> $checklist
 */
class HydraBoardCard extends Model
{
    public const RESOURCE_NAME = 'hydra_board_card';

    protected $table = 'hydra_board_cards';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'column_id', 'name', 'description', 'sort', 'claimed_by', 'claimed_at', 'created_by'];

    protected $casts = [
        'server_id' => 'int',
        'column_id' => 'int',
        'sort' => 'int',
        'claimed_by' => 'int',
        'claimed_at' => 'datetime',
        'created_by' => 'int',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'column_id' => 'required|integer',
        'name' => 'required|string|max:191',
        'description' => 'nullable|string|max:10000',
        'sort' => 'integer|min:0',
        'claimed_by' => 'nullable|integer',
        'created_by' => 'nullable|integer',
    ];

    public function column(): BelongsTo
    {
        return $this->belongsTo(HydraBoardColumn::class, 'column_id');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(HydraBoardTag::class, 'hydra_board_card_tag', 'card_id', 'tag_id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(HydraBoardChecklistItem::class, 'card_id')->orderBy('sort')->orderBy('id');
    }
}
