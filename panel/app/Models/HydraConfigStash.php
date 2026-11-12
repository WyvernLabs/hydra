<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a config file kept in the storage of a server (addon "config_sync").
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string $path where the file was taken from
 * @property string $content
 * @property int $size bytes
 * @property int|null $created_by
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property User|null $creator
 */
class HydraConfigStash extends Model
{
    public const RESOURCE_NAME = 'hydra_config_stash';

    protected $table = 'hydra_config_stash';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'name', 'path', 'content', 'size', 'created_by'];

    protected $casts = [
        'server_id' => 'int',
        'size' => 'int',
        'created_by' => 'int',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'name' => 'required|string|max:64',
        'path' => 'required|string|max:512',
        'content' => 'present|string',
        'size' => 'integer|min:0',
        'created_by' => 'nullable|integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
