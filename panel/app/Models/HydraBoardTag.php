<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: tag of the Kanban board of a server (addon "board"), e.g. a priority.
 * Tags belong to the server and are assigned to its cards.
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string $color
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class HydraBoardTag extends Model
{
    public const RESOURCE_NAME = 'hydra_board_tag';

    protected $table = 'hydra_board_tags';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'name', 'color'];

    protected $casts = [
        'server_id' => 'int',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'name' => 'required|string|max:32',
        'color' => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
