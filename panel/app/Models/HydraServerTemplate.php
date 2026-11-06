<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: named settings for new servers (addon "server_templates"). Only the
 * name and owner of a server are entered when it is created from a template.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $egg_id
 * @property string $image
 * @property string|null $startup null = the startup command of the egg
 * @property array|null $environment
 * @property int $cpu
 * @property string|null $threads
 * @property int $memory
 * @property int $swap
 * @property int $disk
 * @property int $io
 * @property bool $oom_disabled
 * @property int $database_limit
 * @property int $allocation_limit
 * @property int $backup_storage_limit space for backups in MiB
 * @property int[]|null $locations locations to deploy to (public nodes of them)
 * @property int[]|null $nodes nodes to deploy to, also non-public ones
 * @property bool $start_on_completion
 * @property bool $skip_scripts
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Egg $egg
 */
class HydraServerTemplate extends Model
{
    public const RESOURCE_NAME = 'hydra_server_template';

    protected $table = 'hydra_server_templates';

    protected bool $immutableDates = true;

    protected $fillable = [
        'name', 'description', 'egg_id', 'image', 'startup', 'environment', 'cpu', 'threads', 'memory', 'swap', 'disk', 'io',
        'oom_disabled', 'database_limit', 'allocation_limit', 'backup_storage_limit', 'locations', 'nodes', 'start_on_completion', 'skip_scripts',
    ];

    protected $casts = [
        'egg_id' => 'integer',
        'environment' => 'array',
        'cpu' => 'integer',
        'memory' => 'integer',
        'swap' => 'integer',
        'disk' => 'integer',
        'io' => 'integer',
        'oom_disabled' => 'boolean',
        'database_limit' => 'integer',
        'allocation_limit' => 'integer',
        'backup_storage_limit' => 'integer',
        'locations' => 'array',
        'nodes' => 'array',
        'start_on_completion' => 'boolean',
        'skip_scripts' => 'boolean',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,64',
        'description' => 'nullable|string|max:500',
        'egg_id' => 'required|integer|exists:eggs,id',
        'image' => ['required', 'string', 'max:191', 'regex:/^~?[\w\.\/\-:@ ]*$/'],
        'startup' => 'nullable|string',
        'cpu' => 'required|integer|min:0',
        'threads' => 'nullable|regex:/^[0-9-,]+$/',
        'memory' => 'required|integer|min:0',
        'swap' => 'required|integer|min:-1',
        'disk' => 'required|integer|min:0',
        'io' => 'required|integer|between:10,1000',
        'oom_disabled' => 'boolean',
        'database_limit' => 'required|integer|min:0',
        'allocation_limit' => 'required|integer|min:0',
        'backup_storage_limit' => 'required|integer|min:0',
        'start_on_completion' => 'boolean',
        'skip_scripts' => 'boolean',
    ];

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class, 'egg_id');
    }

    /**
     * Names of the locations and nodes its servers go to, empty for all public nodes.
     *
     * @return string[]
     */
    public function targetNames(): array
    {
        return array_merge(
            Location::query()->whereIn('id', $this->locations ?? [])->orderBy('short')->pluck('short')->all(),
            Node::query()->whereIn('id', $this->nodes ?? [])->orderBy('name')->pluck('name')->all(),
        );
    }
}
