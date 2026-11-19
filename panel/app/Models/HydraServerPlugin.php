<?php

namespace Pterodactyl\Models;

/**
 * Hydra: a plugin installed with the plugin manager (addon "plugins").
 *
 * @property int $id
 * @property int $server_id
 * @property string $source modrinth, hangar or spigot
 * @property string $project_id
 * @property string $name
 * @property string $file name of the jar in the plugins folder (without ".disabled")
 * @property string $version_id
 * @property string $version_name
 * @property string|null $icon
 * @property string|null $url page of the plugin
 * @property string|null $software software the version was picked for
 * @property string|null $minecraft Minecraft version the version was picked for
 * @property int|null $installed_by
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class HydraServerPlugin extends Model
{
    public const RESOURCE_NAME = 'hydra_server_plugin';

    protected $table = 'hydra_server_plugins';

    protected bool $immutableDates = true;

    protected $fillable = [
        'server_id', 'source', 'project_id', 'name', 'file', 'version_id', 'version_name',
        'icon', 'url', 'software', 'minecraft', 'installed_by',
    ];

    protected $casts = [
        'server_id' => 'int',
        'installed_by' => 'int',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'source' => 'required|string|in:modrinth,hangar,spigot',
        'project_id' => 'required|string|max:64',
        'name' => 'required|string|max:191',
        'file' => 'required|string|max:191',
        'version_id' => 'required|string|max:191',
        'version_name' => 'required|string|max:191',
        'icon' => 'nullable|string|max:512',
        'url' => 'nullable|string|max:512',
        'software' => 'nullable|string|max:16',
        'minecraft' => 'nullable|string|max:32',
        'installed_by' => 'nullable|integer',
    ];
}
