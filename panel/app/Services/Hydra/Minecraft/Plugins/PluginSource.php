<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Plugins;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: a place plugins come from (addon "plugins"): Modrinth, Hangar or
 * SpigotMC. Every source answers in the same shape, so the frontend does not
 * care where a plugin is from.
 *
 * Project: id, slug, name, author, description, icon (URL), downloads,
 * updated (ISO date), url (page of the plugin), installable, reason
 * ("premium" / "external" when it cannot be installed).
 *
 * Version: id, name, channel (release / beta / alpha), date, downloads, size
 * (bytes), file, game_versions, installable, external_url.
 *
 * Download: url, file, version_id, version_name, name, icon.
 *
 * Downloads are always looked up by the panel, never taken from the browser.
 */
abstract class PluginSource
{
    public const USER_AGENT = 'Hydra-Panel (https://github.com/WyvernLabs/hydra)';

    /**
     * Server software the manager knows, with the family it belongs to.
     */
    public const SOFTWARE = [
        'paper' => 'server',
        'purpur' => 'server',
        'folia' => 'server',
        'spigot' => 'server',
        'bukkit' => 'server',
        'velocity' => 'proxy',
        'waterfall' => 'proxy',
        'bungeecord' => 'proxy',
    ];

    abstract public function key(): string;

    /**
     * Whether the source has plugins for the software.
     */
    abstract public function supports(string $software): bool;

    /**
     * @return array{data: array<int, array>, total: int}
     */
    abstract public function search(string $query, string $software, ?string $version, string $sort, int $page, int $perPage): array;

    /**
     * Versions of a project that fit the software and Minecraft version, newest first.
     *
     * @return array<int, array>
     */
    abstract public function versions(string $project, string $software, ?string $version): array;

    /**
     * File of a version (or of the newest fitting one when $versionId is null).
     *
     * @throws DisplayException when it cannot be installed
     */
    abstract public function download(string $project, ?string $versionId, string $software, ?string $version): array;

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
     * GET request with a short cache; failures become a friendly message.
     */
    protected function get(string $url, array $query = [], int $minutes = 10): array
    {
        $key = 'hydra:plugins:' . $this->key() . ':' . sha1($url . '?' . http_build_query($query));

        return Cache::remember($key, now()->addMinutes($minutes), function () use ($url, $query) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json'])
                    ->timeout(15)
                    ->get($url, $query);
            } catch (ConnectionException) {
                throw new DisplayException(__('server/plugins.errors.unreachable', ['source' => $this->name()]));
            }

            if ($response->status() === 404) {
                throw new DisplayException(__('server/plugins.errors.not_found'));
            }
            if (!$response->successful() || !is_array($response->json())) {
                throw new DisplayException(__('server/plugins.errors.unreachable', ['source' => $this->name()]));
            }

            return $response->json();
        });
    }

    public function name(): string
    {
        return __("server/plugins.sources.{$this->key()}");
    }

    /**
     * A file name that is safe on the node and ends in ".jar".
     */
    public static function fileName(string $name, string $fallback): string
    {
        $name = preg_replace('/[^A-Za-z0-9._+-]/', '-', basename(rawurldecode($name))) ?? '';
        $name = trim($name, '.-');
        if ($name === '') {
            $name = preg_replace('/[^A-Za-z0-9._+-]/', '-', $fallback) ?: 'plugin';
        }
        if (!str_ends_with(strtolower($name), '.jar')) {
            $name .= '.jar';
        }

        return mb_substr($name, -120);
    }

    /**
     * "1.21.4" → "1.21", used where sources only know major versions.
     */
    protected static function major(?string $version): ?string
    {
        return $version && preg_match('/^(\d+\.\d+)/', $version, $m) ? $m[1] : $version;
    }
}
