<?php

namespace Pterodactyl\Models;

/**
 * Hydra: a prepared reason and duration for banning a user account.
 *
 * @property int $id
 * @property string $name
 * @property string $reason
 * @property int|null $duration_days
 * @property int $sort
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BanTemplate extends Model
{
    public const RESOURCE_NAME = 'ban_template';

    protected $table = 'ban_templates';

    protected $fillable = ['name', 'reason', 'duration_days', 'sort'];

    protected $casts = [
        'duration_days' => 'integer',
        'sort' => 'integer',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,191',
        'reason' => 'required|string|max:2000',
        'duration_days' => 'nullable|integer|between:1,3650',
        'sort' => 'integer|min:0',
    ];
}
