<?php

namespace Pterodactyl\Models;

/**
 * Hydra: a mod installed with the mod manager (addon "mods").
 *
 * @property int $id
 * @property int $server_id
 * @property string $source modrinth or curseforge
 * @property string $project_id
 * @property string $name
 * @property string $file name of the jar in the mods folder (without ".disabled")
 * @property string $version_id
 * @property string $version_name
 * @property string|null $icon
 * @property string|null $url page of the mod
 * @property string|null $loader mod loader the version was picked for
 * @property string|null $minecraft Minecraft version the version was picked for
 * @property bool $dependency installed because another mod needs it
 * @property int|null $installed_by
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class HydraServerMod extends Model
{
    public const RESOURCE_NAME = 'hydra_server_mod';

    protected $table = 'hydra_server_mods';

    protected bool $immutableDates = true;

    protected $fillable = [
        'server_id', 'source', 'project_id', 'name', 'file', 'version_id', 'version_name',
        'icon', 'url', 'loader', 'minecraft', 'dependency', 'installed_by',
    ];

    protected $casts = [
        'server_id' => 'int',
        'dependency' => 'bool',
        'installed_by' => 'int',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer',
        'source' => 'required|string|in:modrinth,curseforge',
        'project_id' => 'required|string|max:64',
        'name' => 'required|string|max:191',
        'file' => 'required|string|max:191',
        'version_id' => 'required|string|max:191',
        'version_name' => 'required|string|max:191',
        'icon' => 'nullable|string|max:512',
        'url' => 'nullable|string|max:512',
        'loader' => 'nullable|string|max:16',
        'minecraft' => 'nullable|string|max:32',
        'dependency' => 'boolean',
        'installed_by' => 'nullable|integer',
    ];
}
