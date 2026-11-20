<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Mods;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\HydraServerMod;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\ModsAddon;
use Pterodactyl\Services\Hydra\Addons\TrashAddon;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: mod manager (addon "mods"). Finds the mods folder of a server, lists
 * the jars in it, installs mods from Modrinth and CurseForge through the node
 * (with their required dependencies) and switches them on and off ("x.jar" ↔
 * "x.jar.disabled", which no mod loader loads).
 */
class ModManagerService
{
    // Longest time a single download may take, in seconds.
    public const DOWNLOAD_TIMEOUT = 300;

    // Most mods installed in one go: the mod and its dependencies.
    public const MAX_DEPENDENCIES = 10;

    private const DISABLED = '.disabled';

    public function __construct(private DaemonFileRepository $files, private ModsAddon $addon)
    {
    }

    /**
     * @return array<string, ModSource> enabled sources keyed by key
     */
    public function sources(): array
    {
        $sources = ['modrinth' => app(ModrinthModSource::class)];
        if ($this->addon->hasCurseForge()) {
            $sources['curseforge'] = app(CurseForgeSource::class);
        }

        return $sources;
    }

    public function source(string $key): ModSource
    {
        return $this->sources()[$key] ?? throw new DisplayException(__('server/mods.errors.unknown_source'));
    }

    /**
     * Root directory of the server: name of the mods folder ("mods", any
     * case, null when there is none) and the names of all entries, which tell
     * the loader. Cached for a minute.
     *
     * @return array{name: ?string, entries: string[]}
     */
    private function root(Server $server, bool $fresh = false): array
    {
        $key = "hydra:mods:root:{$server->uuid}";
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinute(), function () use ($server) {
            try {
                $entries = $this->files->setServer($server)->getDirectory('/');
            } catch (DaemonConnectionException) {
                return ['name' => null, 'entries' => []];
            }
            $name = null;
            foreach ($entries as $entry) {
                if (!empty($entry['directory']) && strtolower((string) ($entry['name'] ?? '')) === 'mods') {
                    $name = (string) $entry['name'];
                }
            }

            return ['name' => $name, 'entries' => array_slice(array_map(fn ($entry) => (string) ($entry['name'] ?? ''), $entries), 0, 500)];
        });
    }

    public function folder(Server $server, bool $fresh = false): ?string
    {
        return $this->root($server, $fresh)['name'];
    }

    public function requireFolder(Server $server): string
    {
        return $this->folder($server, true) ?? throw new DisplayException(__('server/mods.errors.no_folder'));
    }

    /**
     * Jars in the mods folder, with what the panel knows about them. Records
     * of jars that were removed elsewhere (file manager, SFTP) go.
     *
     * @return array<int, array>
     */
    public function installed(Server $server): array
    {
        $folder = $this->requireFolder($server);
        $entries = $this->files->setServer($server)->getDirectory('/' . $folder);

        $records = HydraServerMod::query()->where('server_id', $server->id)->get()->keyBy('file');
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
                'mod' => $record ? [
                    'source' => $record->source,
                    'project_id' => $record->project_id,
                    'name' => $record->name,
                    'version_id' => $record->version_id,
                    'version_name' => $record->version_name,
                    'icon' => $record->icon,
                    'url' => $record->url,
                    'dependency' => $record->dependency,
                    'installed_at' => $record->updated_at->toIso8601String(),
                ] : null,
            ];
        }

        // Whatever is left was removed from the folder.
        if ($records->isNotEmpty()) {
            HydraServerMod::query()->whereIn('id', $records->pluck('id'))->delete();
        }

        usort($list, fn (array $a, array $b) => strcasecmp($a['mod']['name'] ?? $a['file'], $b['mod']['name'] ?? $b['file']));

        return $list;
    }

    /**
     * Installs a mod or updates it when it was installed from the same
     * project before, and then the required dependencies that are not there
     * yet (when the addon allows it). Returns the record of the mod and the
     * records of the dependencies that were installed.
     *
     * @return array{0: HydraServerMod, 1: HydraServerMod[]}
     *
     * @throws DisplayException
     */
    public function install(Server $server, ?User $user, string $sourceKey, string $project, ?string $versionId, string $loader, ?string $version): array
    {
        $this->assertLoader($loader);
        $source = $this->source($sourceKey);
        $folder = $this->requireFolder($server);

        $record = $this->installOne($server, $user, $source, $folder, $project, $versionId, $loader, $version, false, $dependencies);

        $installed = [];
        if ($this->addon->get('dependencies', true)) {
            $seen = [$record->project_id => true];
            $queue = $dependencies;
            while (!empty($queue) && count($installed) < self::MAX_DEPENDENCIES) {
                $dependency = array_shift($queue);
                if (isset($seen[$dependency])) {
                    continue;
                }
                $seen[$dependency] = true;
                $present = HydraServerMod::query()
                    ->where('server_id', $server->id)->where('source', $source->key())->where('project_id', $dependency)->exists();
                if ($present) {
                    continue;
                }
                try {
                    $installed[] = $this->installOne($server, $user, $source, $folder, $dependency, null, $loader, $version, true, $more);
                    array_push($queue, ...$more);
                } catch (DisplayException) {
                    // A dependency without a fitting version does not stop the mod.
                }
            }
        }

        return [$record, $installed];
    }

    /**
     * @param string[]|null $dependencies set to the required dependencies of the installed version
     */
    private function installOne(Server $server, ?User $user, ModSource $source, string $folder, string $project, ?string $versionId, string $loader, ?string $version, bool $dependency, ?array &$dependencies): HydraServerMod
    {
        $download = $source->download($project, $versionId, $loader, $version);
        $this->assertDownload($source, $download['url']);
        $dependencies = $download['dependencies'] ?? [];

        $files = $this->files->setServer($server);
        $previous = HydraServerMod::query()
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
            throw new DisplayException(__('server/mods.errors.download_failed'), $exception);
        }

        if ($previous && $previous->file !== $download['file']) {
            // The old jar goes, in whatever state it was.
            $this->deleteJar($server, $folder, $previous->file);
        }
        if ($wasDisabled) {
            $files->renameFiles('/' . $folder, [['from' => $download['file'], 'to' => $download['file'] . self::DISABLED]]);
        }

        $previous?->delete();
        HydraServerMod::query()->where('server_id', $server->id)->where('file', $download['file'])->delete();

        return HydraServerMod::query()->create([
            'server_id' => $server->id,
            'source' => $source->key(),
            'project_id' => $download['project_id'],
            'name' => mb_substr($download['name'], 0, 191),
            'file' => $download['file'],
            'version_id' => mb_substr($download['version_id'], 0, 191),
            'version_name' => mb_substr($download['version_name'], 0, 191),
            'icon' => $download['icon'] ? mb_substr($download['icon'], 0, 512) : null,
            'url' => mb_substr($download['url_page'], 0, 512),
            'loader' => $loader,
            'minecraft' => $version ? mb_substr($version, 0, 32) : null,
            // An update keeps whether the mod came in as a dependency.
            'dependency' => $previous ? ($previous->dependency && $dependency) : $dependency,
            'installed_by' => $user?->id,
        ]);
    }

    /**
     * Switches a mod on or off by renaming its jar.
     */
    public function toggle(Server $server, string $file, bool $enabled): void
    {
        $folder = $this->requireFolder($server);
        $file = $this->assertFile($file);
        [$from, $to] = $enabled ? [$file . self::DISABLED, $file] : [$file, $file . self::DISABLED];
        if (!$this->exists($server, $folder, $from)) {
            throw new DisplayException(__('server/mods.errors.file_missing'));
        }

        $this->files->setServer($server)->renameFiles('/' . $folder, [['from' => $from, 'to' => $to]]);
    }

    /**
     * Deletes a mod jar (its configs in /config stay). It goes into the trash
     * while the trash addon is on.
     */
    public function delete(Server $server, string $file): ?HydraServerMod
    {
        $folder = $this->requireFolder($server);
        $file = $this->assertFile($file);
        $this->deleteJar($server, $folder, $file, app(TrashAddon::class)->keepSeconds());

        $record = HydraServerMod::query()->where('server_id', $server->id)->where('file', $file)->first();
        $record?->delete();

        return $record;
    }

    /**
     * Newer versions of the mods the panel installed, keyed by file.
     *
     * @return array<string, array{version_id: string, version_name: string}>
     */
    public function updates(Server $server, string $loader, ?string $version): array
    {
        $this->assertLoader($loader);
        $sources = $this->sources();
        $updates = [];
        foreach (HydraServerMod::query()->where('server_id', $server->id)->get() as $record) {
            $source = $sources[$record->source] ?? null;
            if (!$source) {
                continue;
            }
            try {
                $latest = $source->download($record->project_id, null, $loader, $version);
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
     * Loader and Minecraft version the server most likely runs: from the
     * version changer, the files in its root, the egg and its variables.
     *
     * @return array{loader: string, version: ?string}
     */
    public function defaults(Server $server): array
    {
        $installed = $server->hydra_minecraft;
        $loader = $this->loaderFrom(strtolower((string) ($installed['type'] ?? '')));
        // Files in the root tell what is really installed, e.g. "fabric-server-launch.jar",
        // ".fabric" or "neoforge-21.1.77-installer.jar.log"; the egg name comes after.
        $loader ??= $this->loaderFrom(strtolower(implode(' ', $this->root($server)['entries'])));
        $loader ??= $this->loaderFrom(strtolower((string) $server->loadMissing('egg')->egg?->name));
        $loader ??= 'fabric';

        $version = $installed['version'] ?? null;
        if (!is_string($version) || !preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) {
            $version = null;
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

        return ['loader' => $loader, 'version' => $version];
    }

    private function loaderFrom(string $text): ?string
    {
        // NeoForge before Forge, Quilt before Fabric (Quilt servers mention Fabric too).
        foreach (['neoforge', 'quilt', 'fabric', 'forge'] as $loader) {
            if (str_contains($text, $loader)) {
                return $loader;
            }
        }

        return null;
    }

    public function assertLoader(string $loader): void
    {
        if (!in_array($loader, ModSource::LOADERS, true)) {
            throw new DisplayException(__('server/mods.errors.unknown_loader'));
        }
    }

    /**
     * Downloads only come over https from the hosts of their source.
     */
    private function assertDownload(ModSource $source, string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), $source->downloadHosts(), true)
            || isset($parts['user']) || isset($parts['port'])) {
            throw new DisplayException(__('server/mods.errors.download_failed'));
        }
    }

    private function assertFile(string $file): string
    {
        $file = basename(str_replace('\\', '/', $file));
        if (!preg_match('/^[^\/\0]{1,180}\.jar$/i', $file)) {
            throw new DisplayException(__('server/mods.errors.file_missing'));
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
