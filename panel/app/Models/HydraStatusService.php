<?php

namespace Pterodactyl\Models;

/**
 * Hydra: an own application on the status page (addon "status").
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $target a URL for "http", host:port for "tcp"; never shown publicly
 * @property bool $enabled
 * @property int $sort
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class HydraStatusService extends Model
{
    public const RESOURCE_NAME = 'hydra_status_service';

    public const TYPE_HTTP = 'http';
    public const TYPE_TCP = 'tcp';

    protected $table = 'hydra_status_services';

    protected bool $immutableDates = true;

    protected $fillable = ['name', 'type', 'target', 'enabled', 'sort'];

    protected $casts = [
        'enabled' => 'boolean',
        'sort' => 'integer',
    ];

    public static array $validationRules = [
        'name' => 'required|string|between:1,64',
        'type' => 'required|string|in:http,tcp',
        'target' => 'required|string|max:2048',
        'enabled' => 'boolean',
        'sort' => 'integer|min:0',
    ];

    /**
     * Key of its results in hydra_status_checks.
     */
    public function statusKey(): string
    {
        return 'service:' . $this->id;
    }
}
