<?php

namespace Pterodactyl\Models;

/**
 * Hydra: an installation of a modpack (addon "modpacks"), with its progress.
 *
 * @property int $id
 * @property int $server_id
 * @property int|null $user_id
 * @property string $source modrinth or curseforge
 * @property string $project_id
 * @property string $version_id
 * @property string $name
 * @property string|null $version_name
 * @property string|null $icon
 * @property string|null $url
 * @property string $mode wipe, profile or keep
 * @property bool $eula
 * @property string $status queued, running, done or failed
 * @property string|null $step prepare, clear, server, overrides, mods, finish
 * @property int $progress
 * @property int $total
 * @property array|null $result minecraft, loader, loader_version, skipped, manual, failed
 * @property string|null $error
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class HydraModpackInstall extends Model
{
    public const RESOURCE_NAME = 'hydra_modpack_install';

    // A running install that did not report for this long has died (e.g. the worker restarted).
    public const STALE_MINUTES = 20;

    protected $table = 'hydra_modpack_installs';

    protected bool $immutableDates = true;

    protected $fillable = [
        'server_id', 'user_id', 'source', 'project_id', 'version_id', 'name', 'version_name', 'icon', 'url',
        'mode', 'eula', 'status', 'step', 'progress', 'total', 'result', 'error',
    ];

    protected $casts = [
        'server_id' => 'int',
        'user_id' => 'int',
        'eula' => 'bool',
        'progress' => 'int',
        'total' => 'int',
        'result' => 'array',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'source' => 'required|string|in:modrinth,curseforge',
        'project_id' => 'required|string|max:64',
        'version_id' => 'required|string|max:191',
        'name' => 'required|string|max:191',
        'mode' => 'required|string|in:wipe,profile,keep',
        'status' => 'required|string|in:queued,running,done,failed',
    ];

    public function server(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Whether the install still runs (and has not died silently).
     */
    public function active(): bool
    {
        return in_array($this->status, ['queued', 'running'], true)
            && $this->updated_at->gt(now()->subMinutes(self::STALE_MINUTES));
    }

    public function toFrontend(): array
    {
        $status = in_array($this->status, ['queued', 'running'], true) && !$this->active() ? 'failed' : $this->status;

        return [
            'id' => $this->id,
            'source' => $this->source,
            'project_id' => $this->project_id,
            'version_id' => $this->version_id,
            'name' => $this->name,
            'version_name' => $this->version_name,
            'icon' => $this->icon,
            'url' => $this->url,
            'mode' => $this->mode,
            'status' => $status,
            'step' => $this->step,
            'progress' => $this->progress,
            'total' => $this->total,
            'result' => $this->result ?? (object) [],
            'error' => $status === 'failed' && $this->status !== 'failed' ? __('server/modpacks.errors.stalled') : $this->error,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
