<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: column of the Kanban board of a server (addon "board").
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string|null $color
 * @property int $sort
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Server $server
 * @property \Illuminate\Database\Eloquent\Collection<int, HydraBoardCard> $cards
 */
class HydraBoardColumn extends Model
{
    public const RESOURCE_NAME = 'hydra_board_column';

    protected $table = 'hydra_board_columns';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'name', 'color', 'sort'];

    protected $casts = [
        'server_id' => 'int',
        'sort' => 'int',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'name' => 'required|string|max:64',
        'color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        'sort' => 'integer|min:0',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(HydraBoardCard::class, 'column_id')->orderBy('sort')->orderBy('id');
    }
}
