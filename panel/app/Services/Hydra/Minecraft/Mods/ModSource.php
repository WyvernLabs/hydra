<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Mods;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource;

/**
 * Hydra: a place mods come from (addon "mods"): Modrinth or CurseForge. Every
 * source answers in the same shape, so the frontend does not care where a mod
 * is from.
 *
 * Project: id, slug, name, author, description, icon (URL), downloads,
 * updated (ISO date), url (page of the mod), categories (keys), installable,
 * reason ("external" when the author does not allow downloads elsewhere).
 *
 * Version: id, name, channel (release / beta / alpha), date, downloads, size
 * (bytes), file, game_versions, installable, external_url.
 *
 * Download: url, file, version_id, version_name, project_id, name, icon,
 * url_page, dependencies (project ids of required mods).
 *
 * Category: key, slug (for translations), name, parent (key or null).
 *
 * Downloads are always looked up by the panel, never taken from the browser.
 */
abstract class ModSource
{
    /**
     * Mod loaders the manager knows.
     */
    public const LOADERS = ['fabric', 'forge', 'neoforge', 'quilt'];

    abstract public function key(): string;

    /**
     * @return array{data: array<int, array>, total: int}
     */
    abstract public function search(string $query, string $loader, ?string $version, ?string $category, string $sort, int $page, int $perPage): array;

    /**
     * Versions of a project that fit the loader and Minecraft version, newest first.
     *
     * @return array<int, array>
     */
    abstract public function versions(string $project, string $loader, ?string $version): array;

    /**
     * File of a version (or of the newest fitting one when $versionId is null).
     *
     * @throws DisplayException when it cannot be installed
     */
    abstract public function download(string $project, ?string $versionId, string $loader, ?string $version): array;

    /**
     * Categories of mods of the source.
     *
     * @return array<int, array{key: string, slug: string, name: string, parent: ?string}>
     */
    abstract public function categories(): array;

    /**
     * Hosts the files of the source are downloaded from.
     *
     * @return string[]
     */
    abstract public function downloadHosts(): array;

    /**
     * Hosts the icons of the source are loaded from.
     *
     * @return string[]
     */
    abstract public function iconHosts(): array;

    /**
     * Extra headers of every request, e.g. an API key.
     */
    protected function headers(): array
    {
        return [];
    }

    /**
     * GET request with a short cache; failures become a friendly message.
     * $forbidden is the translation key shown when the source answers 401/403.
     */
    protected function get(string $url, array $query = [], int $minutes = 10, string $forbidden = 'server/mods.errors.key_invalid'): array
    {
        $key = 'hydra:mods:' . $this->key() . ':' . sha1($url . '?' . http_build_query($query));

        return Cache::remember($key, now()->addMinutes($minutes), function () use ($url, $query, $forbidden) {
            try {
                $response = Http::withHeaders(array_merge(
                    ['User-Agent' => PluginSource::USER_AGENT, 'Accept' => 'application/json'],
                    $this->headers()
                ))->timeout(15)->get($url, $query);
            } catch (ConnectionException) {
                throw new DisplayException(__('server/mods.errors.unreachable', ['source' => $this->name()]));
            }

            if ($response->status() === 404) {
                throw new DisplayException(__('server/mods.errors.not_found'));
            }
            if (in_array($response->status(), [401, 403], true)) {
                throw new DisplayException(__($forbidden, ['source' => $this->name()]));
            }
            if (!$response->successful() || !is_array($response->json())) {
                throw new DisplayException(__('server/mods.errors.unreachable', ['source' => $this->name()]));
            }

            return $response->json();
        });
    }

    public function name(): string
    {
        return __("server/mods.sources.{$this->key()}");
    }

    /**
     * A file name that is safe on the node and ends in ".jar".
     */
    public static function fileName(string $name, string $fallback): string
    {
        return PluginSource::fileName($name, $fallback);
    }

    protected function assertProject(string $project): void
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $project)) {
            throw new DisplayException(__('server/mods.errors.not_found'));
        }
    }

    /**
     * The newest release of a list of versions, otherwise the newest of all,
     * or the one with the given id.
     */
    protected static function pick(array $versions, ?string $versionId): ?array
    {
        if (!is_null($versionId)) {
            return collect($versions)->firstWhere('id', $versionId);
        }

        return collect($versions)->firstWhere('channel', 'release') ?? ($versions[0] ?? null);
    }
}
