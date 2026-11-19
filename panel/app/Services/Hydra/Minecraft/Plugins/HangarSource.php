<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Plugins;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: plugins of hangar.papermc.io (https://hangar.papermc.io/api-docs).
 * Hangar knows the platforms Paper (also used for Purpur, Folia, Spigot and
 * Bukkit servers), Velocity and Waterfall.
 */
class HangarSource extends PluginSource
{
    public const API = 'https://hangar.papermc.io/api/v1';

    private const PLATFORMS = [
        'paper' => 'PAPER', 'purpur' => 'PAPER', 'folia' => 'PAPER', 'spigot' => 'PAPER', 'bukkit' => 'PAPER',
        'velocity' => 'VELOCITY', 'waterfall' => 'WATERFALL', 'bungeecord' => 'WATERFALL',
    ];

    private const SORTS = ['relevance' => null, 'downloads' => '-downloads', 'updated' => '-updated', 'newest' => '-newest'];

    public function key(): string
    {
        return 'hangar';
    }

    public function supports(string $software): bool
    {
        return isset(self::PLATFORMS[$software]);
    }

    public function search(string $query, string $software, ?string $version, string $sort, int $page, int $perPage): array
    {
        $params = array_filter([
            'q' => $query !== '' ? $query : null,
            'platform' => self::PLATFORMS[$software],
            'version' => $version && self::PLATFORMS[$software] === 'PAPER' ? $version : null,
            'sort' => self::SORTS[$sort] ?? ($query === '' ? '-downloads' : null),
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ], fn ($value) => !is_null($value));

        $data = $this->get(self::API . '/projects', $params);

        return [
            'data' => array_map(fn (array $project) => [
                'id' => (string) $project['id'],
                'slug' => (string) ($project['namespace']['slug'] ?? $project['name']),
                'name' => (string) $project['name'],
                'author' => (string) ($project['namespace']['owner'] ?? ''),
                'description' => (string) ($project['description'] ?? ''),
                'icon' => $project['avatarUrl'] ?? null,
                'downloads' => (int) ($project['stats']['downloads'] ?? 0),
                'updated' => $project['lastUpdated'] ?? null,
                'url' => 'https://hangar.papermc.io/' . rawurlencode((string) ($project['namespace']['owner'] ?? '')) . '/' . rawurlencode((string) ($project['namespace']['slug'] ?? $project['name'])),
                'installable' => true,
                'reason' => null,
            ], $data['result'] ?? []),
            'total' => (int) ($data['pagination']['count'] ?? 0),
        ];
    }

    public function versions(string $project, string $software, ?string $version): array
    {
        $this->assertProject($project);
        $platform = self::PLATFORMS[$software] ?? 'PAPER';
        $data = $this->get(self::API . '/projects/' . rawurlencode($project) . '/versions', array_filter([
            'platform' => $platform,
            'platformVersion' => $version && $platform === 'PAPER' ? $version : null,
            'limit' => 25,
        ]), 5);

        return array_values(array_filter(array_map(function (array $entry) use ($platform) {
            $download = $entry['downloads'][$platform] ?? null;
            if (!$download) {
                return null;
            }
            $channel = strtolower((string) ($entry['channel']['name'] ?? 'release'));

            return [
                'id' => (string) $entry['name'],
                'name' => (string) $entry['name'],
                'channel' => $channel === 'release' ? 'release' : ($channel === 'alpha' ? 'alpha' : 'beta'),
                'date' => $entry['createdAt'] ?? null,
                'downloads' => (int) ($entry['stats']['totalDownloads'] ?? 0),
                'size' => (int) ($download['fileInfo']['sizeBytes'] ?? 0),
                'file' => (string) ($download['fileInfo']['name'] ?? ''),
                'game_versions' => array_values(array_slice(array_reverse($entry['platformDependencies'][$platform] ?? []), 0, 6)),
                'installable' => !empty($download['downloadUrl']),
                'external_url' => $download['externalUrl'] ?? null,
                'url' => (string) ($download['downloadUrl'] ?? ''),
            ];
        }, $data['result'] ?? [])));
    }

    public function download(string $project, ?string $versionId, string $software, ?string $version): array
    {
        $versions = $this->versions($project, $software, $version);
        if (!is_null($versionId)) {
            $versions = array_values(array_filter($versions, fn (array $entry) => $entry['id'] === $versionId));
        } else {
            $releases = array_values(array_filter($versions, fn (array $entry) => $entry['channel'] === 'release' && $entry['installable']));
            $versions = $releases ?: array_values(array_filter($versions, fn (array $entry) => $entry['installable']));
        }
        $entry = $versions[0] ?? null;
        if (!$entry) {
            throw new DisplayException(__('server/plugins.errors.no_version'));
        }
        if (!$entry['installable']) {
            throw new DisplayException(__('server/plugins.errors.external'));
        }
        $info = $this->project($project);

        return [
            'url' => $entry['url'],
            'file' => self::fileName($entry['file'], $info['name']),
            'version_id' => $entry['id'],
            'version_name' => $entry['name'],
            'project_id' => $info['id'],
            'name' => $info['name'],
            'icon' => $info['icon'],
            'url_page' => $info['url'],
        ];
    }

    /**
     * @return array{id: string, name: string, icon: ?string, url: string}
     */
    public function project(string $project): array
    {
        $this->assertProject($project);
        $data = $this->get(self::API . '/projects/' . rawurlencode($project), [], 60);

        return [
            'id' => (string) ($data['id'] ?? $project),
            'name' => (string) ($data['name'] ?? $project),
            'icon' => $data['avatarUrl'] ?? null,
            'url' => 'https://hangar.papermc.io/' . rawurlencode((string) ($data['namespace']['owner'] ?? '')) . '/' . rawurlencode((string) ($data['namespace']['slug'] ?? $project)),
        ];
    }

    public function downloadHosts(): array
    {
        return ['hangarcdn.papermc.io'];
    }

    public function iconHosts(): array
    {
        return ['hangarcdn.papermc.io'];
    }

    private function assertProject(string $project): void
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $project)) {
            throw new DisplayException(__('server/plugins.errors.not_found'));
        }
    }
}
