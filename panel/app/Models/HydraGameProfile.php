<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: the files of a game a server switched away from (addon "games"),
 * stored by the node outside of the server directory, and what is needed to
 * switch back to it.
 *
 * @property int $id
 * @property int $server_id
 * @property string $uuid
 * @property string $name
 * @property int|null $egg_id
 * @property string $startup
 * @property string $image
 * @property array<string, string> $variables environment variable => value
 * @property string|null $startup_done
 * @property int $size
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Server $server
 * @property Egg|null $egg
 */
class HydraGameProfile extends Model
{
    public const RESOURCE_NAME = 'hydra_game_profile';

    protected $table = 'hydra_game_profiles';

    protected bool $immutableDates = true;

    protected $fillable = ['server_id', 'uuid', 'name', 'egg_id', 'startup', 'image', 'variables', 'startup_done', 'size'];

    protected $casts = [
        'variables' => 'array',
        'size' => 'int',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'uuid' => 'required|string|max:64',
        'name' => 'required|string|max:191',
        'egg_id' => 'nullable|integer',
        'startup' => 'present|string',
        'image' => 'required|string|max:191',
        'variables' => 'array',
        'startup_done' => 'nullable|string',
        'size' => 'integer|min:0',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }
}
