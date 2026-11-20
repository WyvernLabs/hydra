<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Modpacks;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\HydraServerMod;
use Pterodactyl\Models\HydraModpackInstall;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Jobs\Hydra\InstallModpackJob;
use Pterodactyl\Services\Hydra\Addons\GamesAddon;
use Pterodactyl\Services\Hydra\Addons\ModpacksAddon;
use Pterodactyl\Services\Hydra\Minecraft\McJarsClient;
use Pterodactyl\Services\Hydra\Games\GameSwitchService;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Hydra\Minecraft\VersionInstallService;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: installs a modpack on a server (addon "modpacks"), in a queued job:
 *
 * 1. the panel downloads the pack and reads which Minecraft version, loader
 *    and mods it needs,
 * 2. the files of the server are removed, kept as a game profile, or only
 *    the mods folder is emptied (update keeping the world),
 * 3. the server software in exactly that version comes from mcjars.app,
 *    with the matching Java image (the version changer does the work),
 * 4. the node downloads the pack and its configs ("overrides") are merged in,
 * 5. the node downloads every mod; mods the panel knows go to the mod manager.
 */
class ModpackInstallService
{
    // Folder the pack is unpacked into on the server while it is installed.
    public const WORK_DIR = '.hydra-modpack';

    public const DOWNLOAD_TIMEOUT = 600;

    // mcjars types of the loaders.
    private const TYPES = ['fabric' => 'FABRIC', 'forge' => 'FORGE', 'neoforge' => 'NEOFORGE', 'quilt' => 'QUILT'];

    public function __construct(
        private ModpacksAddon $addon,
        private DaemonFileRepository $files,
        private VersionInstallService $versions,
        private McJarsClient $mcjars,
        private GameSwitchService $games,
    ) {
    }

    /**
     * @return array<string, ModpackSource>
     */
    public function sources(): array
    {
        $sources = ['modrinth' => app(ModrinthPackSource::class)];
        if ($this->addon->hasCurseForge()) {
            $sources['curseforge'] = app(CurseForgePackSource::class);
        }

        return $sources;
    }

    public function source(string $key): ModpackSource
    {
        return $this->sources()[$key] ?? throw new DisplayException(__('server/mods.errors.unknown_source'));
    }

    public function latest(Server $server): ?HydraModpackInstall
    {
        return HydraModpackInstall::query()->where('server_id', $server->id)->orderByDesc('id')->first();
    }

    public function offersProfiles(): bool
    {
        return app(GamesAddon::class)->enabled();
    }

    /**
     * Checks the pack and queues its installation.
     *
     * @throws DisplayException
     */
    public function start(Server $server, ?User $user, string $sourceKey, string $project, string $versionId, string $mode, bool $eula): HydraModpackInstall
    {
        if (!is_null($server->status)) {
            throw new DisplayException(__('server/modpacks.errors.busy'));
        }
        if ($this->latest($server)?->active()) {
            throw new DisplayException(__('server/modpacks.errors.running'));
        }
        if ($mode === 'profile' && !$this->offersProfiles()) {
            throw new DisplayException(__('server/modpacks.errors.no_profiles'));
        }

        $source = $this->source($sourceKey);
        $download = $source->download($project, $versionId, '', null);
        $this->assertHost($download['url'], $source->downloadHosts());

        $install = HydraModpackInstall::query()->create([
            'server_id' => $server->id,
            'user_id' => $user?->id,
            'source' => $source->key(),
            'project_id' => $download['project_id'],
            'version_id' => $download['version_id'],
            'name' => mb_substr($download['name'], 0, 191),
            'version_name' => mb_substr($download['version_name'], 0, 191),
            'icon' => $download['icon'] ? mb_substr($download['icon'], 0, 512) : null,
            'url' => mb_substr($download['url_page'], 0, 512),
            'mode' => $mode,
            'eula' => $eula,
            'status' => 'queued',
        ]);

        // Long installs get their own worker (hydra-queue-long) when the queue runs on Redis.
        $job = new InstallModpackJob($install->id);
        if (config('queue.default') === 'redis') {
            $job->onConnection('redis_long')->onQueue('long');
        }
        dispatch($job);

        return $install;
    }

    /**
     * Runs a queued installation (called by the job).
     */
    public function run(HydraModpackInstall $install): void
    {
        $server = $install->server()->with('egg')->firstOrFail();
        $result = ['skipped' => [], 'manual' => [], 'failed' => []];

        try {
            $this->step($install, 'prepare', ['status' => 'running']);
            $source = $this->source($install->source);
            $download = $source->download($install->project_id, $install->version_id, '', null);
            $this->assertHost($download['url'], $source->downloadHosts());
            $plan = $this->readPlan($source, $download['url']);
            [$buildId, $buildName, $exact] = $this->build($plan);

            $result = array_merge($result, [
                'minecraft' => $plan['minecraft'],
                'loader' => $plan['loader'],
                'loader_version' => $plan['loader_version'],
                'server_build' => $buildName,
                'exact_build' => $exact,
                'skipped' => array_values(array_unique($plan['skipped'] ?? [])),
                'manual' => $plan['manual'] ?? [],
            ]);
            $this->step($install, 'clear', ['total' => count($plan['files']), 'result' => $result]);

            if ($install->mode === 'profile') {
                $this->games->clear($server, true, sprintf('%s (%s)', $server->name, CarbonImmutable::now()->format('Y-m-d H:i')));
            }

            $this->step($install, 'server');
            $this->versions->install($server, $buildId, $install->mode === 'wipe', $install->eula);
            $server->refresh();

            $files = $this->files->setServer($server);
            if ($install->mode === 'keep') {
                // The old mods go, the world and everything else stays.
                $this->delete('/', ['mods']);
            }

            $this->step($install, 'overrides');
            $this->delete('/', [self::WORK_DIR]);
            $files->createDirectory(self::WORK_DIR, '/');
            $files->pull($download['url'], '/' . self::WORK_DIR, ['filename' => 'pack.zip', 'foreground' => true, 'timeout' => self::DOWNLOAD_TIMEOUT]);
            $files->decompressFile('/' . self::WORK_DIR, 'pack.zip');
            $unpacked = collect($files->getDirectory('/' . self::WORK_DIR))->filter(fn ($entry) => !empty($entry['directory']))->pluck('name')->all();
            foreach ($plan['overrides'] as $folder) {
                if (in_array($folder, $unpacked, true)) {
                    $this->merge(self::WORK_DIR . '/' . $folder, '');
                }
            }
            $this->delete('/', [self::WORK_DIR]);

            $this->step($install, 'mods');
            $folders = collect($plan['files'])->map(fn (array $file) => dirname($file['path']))->filter(fn ($dir) => $dir !== '.')->unique();
            foreach ($folders as $folder) {
                try {
                    $files->createDirectory($folder, '/');
                } catch (DaemonConnectionException) {
                    // Exists already.
                }
            }
            $done = 0;
            foreach ($plan['files'] as $file) {
                $directory = dirname($file['path']) === '.' ? '/' : '/' . dirname($file['path']);
                try {
                    $files->pull($file['url'], $directory, ['filename' => basename($file['path']), 'foreground' => true, 'timeout' => 300]);
                } catch (DaemonConnectionException) {
                    $result['failed'][] = basename($file['path']);
                }
                $install->forceFill(['progress' => ++$done, 'result' => $result])->save();
            }

            $this->step($install, 'finish', ['result' => $result]);
            $this->records($server, $install, $plan['files'], $result['failed']);
            $server->forceFill(['hydra_minecraft' => array_merge($server->hydra_minecraft ?? [], ['modpack' => [
                'source' => $install->source,
                'project_id' => $install->project_id,
                'version_id' => $install->version_id,
                'name' => $install->name,
                'version_name' => $install->version_name,
                'icon' => $install->icon,
                'url' => $install->url,
                'installed_at' => CarbonImmutable::now()->toIso8601String(),
            ]])])->save();

            $install->forceFill(['status' => 'done', 'result' => $result])->save();
        } catch (\Throwable $exception) {
            if (!$exception instanceof DisplayException) {
                Log::error('Hydra modpacks: installation failed', ['install' => $install->id, 'exception' => $exception]);
            }
            try {
                $this->files->setServer($server);
                $this->delete('/', [self::WORK_DIR]);
            } catch (\Throwable) {
                // The node may be gone.
            }
            $install->forceFill([
                'status' => 'failed',
                'result' => $result,
                'error' => $exception instanceof DisplayException ? $exception->getMessage() : __('server/modpacks.errors.failed'),
            ])->save();
        }
    }

    /**
     * Downloads the pack to the panel and reads its plan.
     */
    private function readPlan(ModpackSource $source, string $url): array
    {
        $archive = tempnam(sys_get_temp_dir(), 'hydra-pack');
        try {
            $response = Http::withHeaders(['User-Agent' => PluginSource::USER_AGENT])
                ->withOptions(['sink' => $archive])
                ->timeout(self::DOWNLOAD_TIMEOUT)
                ->get($url);
            clearstatcache(true, $archive);
            if ($response->successful() && filesize($archive) === 0) {
                // Faked responses (tests) do not write into the sink.
                file_put_contents($archive, $response->body());
                clearstatcache(true, $archive);
            }
            if (!$response->successful() || filesize($archive) === 0 || filesize($archive) > ModpackSource::ARCHIVE_LIMIT) {
                throw new DisplayException(__('server/modpacks.errors.download_failed'));
            }

            return $source->plan($archive);
        } finally {
            @unlink($archive);
        }
    }

    /**
     * The mcjars build of the server software: exactly the loader version of
     * the pack when mcjars has it, otherwise its newest stable build.
     *
     * @return array{0: int, 1: string, 2: bool}
     */
    public function build(array $plan): array
    {
        $type = $plan['loader'] ? (self::TYPES[$plan['loader']] ?? null) : 'VANILLA';
        if (!$type) {
            throw new DisplayException(__('server/modpacks.errors.unknown_loader'));
        }

        try {
            $builds = $this->mcjars->builds($type, $plan['minecraft']);
        } catch (DisplayException) {
            throw new DisplayException(__('server/modpacks.errors.no_server', ['loader' => $type, 'version' => $plan['minecraft']]));
        }
        $builds = array_values(array_filter($builds, 'is_array'));
        $exact = $plan['loader_version']
            ? collect($builds)->first(fn ($build) => (string) ($build['projectVersionId'] ?? $build['name'] ?? '') === $plan['loader_version'])
            : null;
        $build = $exact ?? collect($builds)->first(fn ($build) => empty($build['experimental'])) ?? ($builds[0] ?? null);
        if (!$build || empty($build['id'])) {
            throw new DisplayException(__('server/modpacks.errors.no_server', ['loader' => $type, 'version' => $plan['minecraft']]));
        }

        return [
            (int) $build['id'],
            trim($type . ' ' . $plan['minecraft'] . ' ' . ($build['projectVersionId'] ?? $build['name'] ?? '')),
            !is_null($exact) || is_null($plan['loader_version']),
        ];
    }

    /**
     * Moves the content of a folder into another one; folders that exist in
     * both are merged, files replace what is there.
     */
    private function merge(string $from, string $to, int $depth = 0): void
    {
        $join = fn (string $dir, string $name) => ltrim(($dir === '' ? '' : $dir . '/') . $name, '/');
        $source = $this->files->getDirectory('/' . $from);
        $target = collect($this->files->getDirectory('/' . $to))->keyBy('name');

        $moves = [];
        foreach ($source as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }
            $existing = $target->get($name);
            if ($existing && !empty($existing['directory']) && !empty($entry['directory']) && $depth < 10) {
                $this->merge($join($from, $name), $join($to, $name), $depth + 1);
                continue;
            }
            if ($existing) {
                $this->delete('/' . $to, [$name]);
            }
            $moves[] = ['from' => $join($from, $name), 'to' => $join($to, $name)];
        }
        foreach (array_chunk($moves, 100) as $chunk) {
            $this->files->renameFiles('/', $chunk);
        }
    }

    private function delete(string $root, array $names): void
    {
        $present = collect($this->files->getDirectory($root))->pluck('name')->intersect($names)->values()->all();
        if (!empty($present)) {
            $this->files->deleteFiles($root, $present);
        }
    }

    /**
     * Mods of the pack the panel knows, so the mod manager offers updates.
     */
    private function records(Server $server, HydraModpackInstall $install, array $files, array $failed): void
    {
        HydraServerMod::query()->where('server_id', $server->id)->delete();
        foreach ($files as $file) {
            $record = $file['record'] ?? null;
            $name = basename($file['path']);
            if (!$record || !str_starts_with($file['path'], 'mods/') || substr_count($file['path'], '/') !== 1 || in_array($name, $failed, true)) {
                continue;
            }
            HydraServerMod::query()->updateOrCreate(['server_id' => $server->id, 'file' => $name], [
                'source' => $record['source'],
                'project_id' => mb_substr($record['project_id'], 0, 64),
                'name' => mb_substr($record['name'], 0, 191),
                'version_id' => mb_substr($record['version_id'], 0, 191),
                'version_name' => mb_substr($record['version_name'], 0, 191),
                'icon' => $record['icon'] ? mb_substr($record['icon'], 0, 512) : null,
                'url' => $record['url'] ? mb_substr($record['url'], 0, 512) : null,
                'loader' => $install->result['loader'] ?? null,
                'minecraft' => $install->result['minecraft'] ?? null,
                'dependency' => false,
                'installed_by' => $install->user_id,
            ]);
        }
    }

    private function step(HydraModpackInstall $install, string $step, array $extra = []): void
    {
        $install->forceFill(array_merge(['step' => $step], $extra))->save();
        $install->touch();
    }

    private function assertHost(string $url, array $hosts): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), $hosts, true) || isset($parts['user']) || isset($parts['port'])) {
            throw new DisplayException(__('server/modpacks.errors.download_failed'));
        }
    }
}
