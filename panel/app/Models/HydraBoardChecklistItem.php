<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: entry of the checklist of a board card (addon "board").
 *
 * @property int $id
 * @property int $card_id
 * @property string $text
 * @property bool $done
 * @property int $sort
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property HydraBoardCard $card
 */
class HydraBoardChecklistItem extends Model
{
    public const RESOURCE_NAME = 'hydra_board_checklist_item';

    protected $table = 'hydra_board_checklist_items';

    protected bool $immutableDates = true;

    protected $fillable = ['card_id', 'text', 'done', 'sort'];

    protected $casts = [
        'card_id' => 'int',
        'done' => 'bool',
        'sort' => 'int',
    ];

    public static array $validationRules = [
        'card_id' => 'required|integer',
        'text' => 'required|string|max:255',
        'done' => 'boolean',
        'sort' => 'integer|min:0',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(HydraBoardCard::class, 'card_id');
    }
}
