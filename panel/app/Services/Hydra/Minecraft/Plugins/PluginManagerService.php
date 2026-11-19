<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Plugins;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\HydraServerPlugin;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\TrashAddon;
use Pterodactyl\Services\Hydra\Addons\PluginsAddon;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: plugin manager (addon "plugins"). Finds the plugins folder of a
 * server, lists the jars in it, installs plugins from Modrinth, Hangar and
 * SpigotMC through the node and switches them on and off ("x.jar" ↔
 * "x.jar.disabled", which the server does not load).
 */
class PluginManagerService
{
    // Longest time a single download may take, in seconds.
    public const DOWNLOAD_TIMEOUT = 300;

    private const DISABLED = '.disabled';

    public function __construct(private DaemonFileRepository $files, private PluginsAddon $addon)
    {
    }

    /**
     * @return array<string, PluginSource> enabled sources keyed by key
     */
    public function sources(): array
    {
        $sources = ['modrinth' => app(ModrinthSource::class)];
        if ($this->addon->get('hangar', true)) {
            $sources['hangar'] = app(HangarSource::class);
        }
        if ($this->addon->get('spigot', true)) {
            $sources['spigot'] = app(SpigotSource::class);
        }

        return $sources;
    }

    public function source(string $key): PluginSource
    {
        return $this->sources()[$key] ?? throw new DisplayException(__('server/plugins.errors.unknown_source'));
    }

    /**
     * Name of the plugins folder in the root of the server ("plugins", any
     * case), null when there is none. Cached for a minute.
     */
    public function folder(Server $server, bool $fresh = false): ?string
    {
        $key = "hydra:plugins:folder:{$server->uuid}";
        if ($fresh) {
            Cache::forget($key);
        }

        // Stored as an array, so "no folder" is cached too.
        return Cache::remember($key, now()->addMinute(), function () use ($server) {
            try {
                $entries = $this->files->setServer($server)->getDirectory('/');
            } catch (DaemonConnectionException) {
                return ['name' => null];
            }
            foreach ($entries as $entry) {
                if (!empty($entry['directory']) && strtolower((string) ($entry['name'] ?? '')) === 'plugins') {
                    return ['name' => (string) $entry['name']];
                }
            }

            return ['name' => null];
        })['name'];
    }

    public function requireFolder(Server $server): string
    {
        return $this->folder($server, true) ?? throw new DisplayException(__('server/plugins.errors.no_folder'));
    }

    /**
     * Jars in the plugins folder, with what the panel knows about them.
     * Records of jars that were removed elsewhere (file manager, SFTP) go.
     *
     * @return array<int, array>
     */
    public function installed(Server $server): array
    {
        $folder = $this->requireFolder($server);
        $entries = $this->files->setServer($server)->getDirectory('/' . $folder);

        $records = HydraServerPlugin::query()->where('server_id', $server->id)->get()->keyBy('file');
        $list = [];
        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if (!empty($entry['directory']) || !preg_match('/\.jar(' . preg_quote(self::DISABLED, '/') . ')?$/i', $name)) {
                continue;
            }
            $enabled = !str_ends_with(strtolower($name), self::DISABLED);
            $file = $enabled ? $name : substr($name, 0, -strlen(self::DISABLED));
            $record = $records->pull($file);
            $list[] = [
                'file' => $file,
                'enabled' => $enabled,
                'size' => (int) ($entry['size'] ?? 0),
                'modified' => $entry['modified'] ?? null,
                'plugin' => $record ? [
                    'source' => $record->source,
                    'project_id' => $record->project_id,
                    'name' => $record->name,
                    'version_id' => $record->version_id,
                    'version_name' => $record->version_name,
                    'icon' => $record->icon,
                    'url' => $record->url,
                    'installed_at' => $record->updated_at->toIso8601String(),
                ] : null,
            ];
        }

        // Whatever is left was removed from the folder.
        if ($records->isNotEmpty()) {
            HydraServerPlugin::query()->whereIn('id', $records->pluck('id'))->delete();
        }

        usort($list, fn (array $a, array $b) => strcasecmp($a['plugin']['name'] ?? $a['file'], $b['plugin']['name'] ?? $b['file']));

        return $list;
    }

    /**
     * Installs a plugin or updates it when it was installed from the same
     * project before. Returns the stored record.
     *
     * @throws DisplayException
     */
    public function install(Server $server, ?User $user, string $sourceKey, string $project, ?string $versionId, string $software, ?string $version): HydraServerPlugin
    {
        $this->assertSoftware($software);
        $source = $this->source($sourceKey);
        if (!$source->supports($software)) {
            throw new DisplayException(__('server/plugins.errors.unsupported', ['source' => $source->name()]));
        }
        $folder = $this->requireFolder($server);
        $download = $source->download($project, $versionId, $software, $version);
        $this->assertDownload($source, $download['url']);

        $files = $this->files->setServer($server);
        $previous = HydraServerPlugin::query()
            ->where('server_id', $server->id)
            ->where('source', $source->key())
            ->where('project_id', $download['project_id'])
            ->first();
        $wasDisabled = $previous && $this->exists($server, $folder, $previous->file . self::DISABLED);

        try {
            $files->pull($download['url'], '/' . $folder, [
                'filename' => $download['file'],
                'foreground' => true,
                'timeout' => self::DOWNLOAD_TIMEOUT,
            ]);
        } catch (DaemonConnectionException $exception) {
            throw new DisplayException(__('server/plugins.errors.download_failed'), $exception);
        }

        if ($previous && $previous->file !== $download['file']) {
            // The old jar goes, in whatever state it was.
            $this->deleteJar($server, $folder, $previous->file);
        }
        if ($wasDisabled) {
            $files->renameFiles('/' . $folder, [['from' => $download['file'], 'to' => $download['file'] . self::DISABLED]]);
        }

        $previous?->delete();
        HydraServerPlugin::query()->where('server_id', $server->id)->where('file', $download['file'])->delete();

        return HydraServerPlugin::query()->create([
            'server_id' => $server->id,
            'source' => $source->key(),
            'project_id' => $download['project_id'],
            'name' => mb_substr($download['name'], 0, 191),
            'file' => $download['file'],
            'version_id' => mb_substr($download['version_id'], 0, 191),
            'version_name' => mb_substr($download['version_name'], 0, 191),
            'icon' => $download['icon'] ? mb_substr($download['icon'], 0, 512) : null,
            'url' => mb_substr($download['url_page'], 0, 512),
            'software' => $software,
            'minecraft' => $version ? mb_substr($version, 0, 32) : null,
            'installed_by' => $user?->id,
        ]);
    }

    /**
     * Switches a plugin on or off by renaming its jar.
     */
    public function toggle(Server $server, string $file, bool $enabled): void
    {
        $folder = $this->requireFolder($server);
        $file = $this->assertFile($file);
        [$from, $to] = $enabled ? [$file . self::DISABLED, $file] : [$file, $file . self::DISABLED];
        if (!$this->exists($server, $folder, $from)) {
            throw new DisplayException(__('server/plugins.errors.file_missing'));
        }

        $this->files->setServer($server)->renameFiles('/' . $folder, [['from' => $from, 'to' => $to]]);
    }

    /**
     * Deletes a plugin jar (the folder with its configs stays). It goes into
     * the trash while the trash addon is on.
     */
    public function delete(Server $server, string $file): ?HydraServerPlugin
    {
        $folder = $this->requireFolder($server);
        $file = $this->assertFile($file);
        $this->deleteJar($server, $folder, $file, app(TrashAddon::class)->keepSeconds());

        $record = HydraServerPlugin::query()->where('server_id', $server->id)->where('file', $file)->first();
        $record?->delete();

        return $record;
    }

    /**
     * Newer versions of the plugins the panel installed, keyed by file.
     *
     * @return array<string, array{version_id: string, version_name: string}>
     */
    public function updates(Server $server, string $software, ?string $version): array
    {
        $this->assertSoftware($software);
        $sources = $this->sources();
        $updates = [];
        foreach (HydraServerPlugin::query()->where('server_id', $server->id)->get() as $record) {
            $source = $sources[$record->source] ?? null;
            if (!$source || !$source->supports($software)) {
                continue;
            }
            try {
                $latest = $source->download($record->project_id, null, $software, $version);
            } catch (DisplayException) {
                continue;
            }
            if ($latest['version_id'] !== $record->version_id) {
                $updates[$record->file] = ['version_id' => $latest['version_id'], 'version_name' => $latest['version_name']];
            }
        }

        return $updates;
    }

    /**
     * Software and Minecraft version the server most likely runs: from the
     * version changer, otherwise from the egg and its variables.
     *
     * @return array{software: string, version: ?string}
     */
    public function defaults(Server $server): array
    {
        $software = null;
        $installed = $server->hydra_minecraft;
        $type = strtolower((string) ($installed['type'] ?? ''));
        $map = ['craftbukkit' => 'bukkit', 'bungee' => 'bungeecord'];
        $type = $map[$type] ?? $type;
        if (array_key_exists($type, PluginSource::SOFTWARE)) {
            $software = $type;
        }
        if (!$software) {
            $egg = strtolower((string) $server->loadMissing('egg')->egg?->name);
            foreach (['purpur', 'folia', 'paper', 'spigot', 'bukkit', 'velocity', 'waterfall', 'bungee'] as $name) {
                if (str_contains($egg, $name)) {
                    $software = $map[$name] ?? $name;
                    break;
                }
            }
        }
        $software ??= 'paper';

        $version = null;
        if (PluginSource::SOFTWARE[$software] === 'server') {
            $version = $installed['version'] ?? null;
            if (!$version) {
                foreach ($server->loadMissing('variables')->variables as $variable) {
                    if (in_array($variable->env_variable, ['MINECRAFT_VERSION', 'MC_VERSION', 'VERSION'], true)) {
                        $value = (string) ($variable->server_value ?? $variable->default_value);
                        if (preg_match('/^\d+\.\d+(\.\d+)?$/', $value)) {
                            $version = $value;
                            break;
                        }
                    }
                }
            }
        }

        return ['software' => $software, 'version' => is_string($version) && preg_match('/^\d+\.\d+(\.\d+)?$/', $version) ? $version : null];
    }

    public function assertSoftware(string $software): void
    {
        if (!array_key_exists($software, PluginSource::SOFTWARE)) {
            throw new DisplayException(__('server/plugins.errors.unknown_software'));
        }
    }

    /**
     * Downloads only come over https from the hosts of their source.
     */
    private function assertDownload(PluginSource $source, string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), $source->downloadHosts(), true)) {
            throw new DisplayException(__('server/plugins.errors.download_failed'));
        }
    }

    private function assertFile(string $file): string
    {
        $file = basename(str_replace('\\', '/', $file));
        if (!preg_match('/^[^\/\0]{1,180}\.jar$/i', $file)) {
            throw new DisplayException(__('server/plugins.errors.file_missing'));
        }

        return $file;
    }

    private function exists(Server $server, string $folder, string $name): bool
    {
        try {
            $entries = $this->files->setServer($server)->getDirectory('/' . $folder);
        } catch (DaemonConnectionException) {
            return false;
        }

        return collect($entries)->contains(fn ($entry) => ($entry['name'] ?? null) === $name && empty($entry['directory']));
    }

    private function deleteJar(Server $server, string $folder, string $file, ?int $trash = null): void
    {
        $present = collect($this->files->setServer($server)->getDirectory('/' . $folder))
            ->filter(fn ($entry) => empty($entry['directory']) && in_array($entry['name'] ?? null, [$file, $file . self::DISABLED], true))
            ->pluck('name')
            ->values()
            ->all();
        if (!empty($present)) {
            $this->files->deleteFiles('/' . $folder, $present, $trash);
        }
    }
}
