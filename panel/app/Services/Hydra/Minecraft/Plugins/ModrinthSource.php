<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Plugins;

use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: plugins of modrinth.com (https://docs.modrinth.com/api/).
 */
class ModrinthSource extends PluginSource
{
    public const API = 'https://api.modrinth.com/v2';

    private const SORTS = ['relevance' => 'relevance', 'downloads' => 'downloads', 'updated' => 'updated', 'newest' => 'newest'];

    public function key(): string
    {
        return 'modrinth';
    }

    public function supports(string $software): bool
    {
        return array_key_exists($software, self::SOFTWARE);
    }

    public function search(string $query, string $software, ?string $version, string $sort, int $page, int $perPage): array
    {
        $facets = [['project_types:plugin'], ['categories:' . $software]];
        if ($version && self::SOFTWARE[$software] === 'server') {
            $facets[] = ['versions:' . $version];
        }

        $data = $this->get(self::API . '/search', [
            'query' => $query,
            'facets' => json_encode($facets),
            'index' => self::SORTS[$sort] ?? 'relevance',
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]);

        return [
            'data' => array_map(fn (array $hit) => [
                'id' => (string) $hit['project_id'],
                'slug' => (string) ($hit['slug'] ?? $hit['project_id']),
                'name' => (string) ($hit['title'] ?? $hit['slug'] ?? ''),
                'author' => (string) ($hit['author'] ?? ''),
                'description' => (string) ($hit['description'] ?? ''),
                'icon' => $hit['icon_url'] ?: null,
                'downloads' => (int) ($hit['downloads'] ?? 0),
                'updated' => $hit['date_modified'] ?? null,
                'url' => 'https://modrinth.com/plugin/' . rawurlencode((string) ($hit['slug'] ?? $hit['project_id'])),
                'installable' => true,
                'reason' => null,
            ], $data['hits'] ?? []),
            'total' => (int) ($data['total_hits'] ?? 0),
        ];
    }

    public function versions(string $project, string $software, ?string $version): array
    {
        $this->assertProject($project);
        $query = ['loaders' => json_encode([$software])];
        if ($version && self::SOFTWARE[$software] === 'server') {
            $query['game_versions'] = json_encode([$version]);
        }

        $versions = $this->get(self::API . '/project/' . rawurlencode($project) . '/version', $query, 5);

        return array_values(array_filter(array_map(function (array $entry) {
            $file = collect($entry['files'] ?? [])->firstWhere('primary', true) ?? ($entry['files'][0] ?? null);
            if (!$file) {
                return null;
            }

            return [
                'id' => (string) $entry['id'],
                'name' => (string) ($entry['version_number'] ?? $entry['name'] ?? $entry['id']),
                'channel' => in_array($entry['version_type'] ?? '', ['beta', 'alpha'], true) ? $entry['version_type'] : 'release',
                'date' => $entry['date_published'] ?? null,
                'downloads' => (int) ($entry['downloads'] ?? 0),
                'size' => (int) ($file['size'] ?? 0),
                'file' => (string) ($file['filename'] ?? ''),
                'game_versions' => array_values(array_slice(array_reverse($entry['game_versions'] ?? []), 0, 6)),
                'installable' => true,
                'external_url' => null,
                'url' => (string) ($file['url'] ?? ''),
            ];
        }, is_array($versions) ? $versions : [])));
    }

    public function download(string $project, ?string $versionId, string $software, ?string $version): array
    {
        $versions = $this->versions($project, $software, $version);
        if (!is_null($versionId)) {
            $versions = array_values(array_filter($versions, fn (array $entry) => $entry['id'] === $versionId));
        } else {
            // The newest release, otherwise the newest of all.
            $releases = array_values(array_filter($versions, fn (array $entry) => $entry['channel'] === 'release'));
            $versions = $releases ?: $versions;
        }
        $entry = $versions[0] ?? null;
        if (!$entry || $entry['url'] === '') {
            throw new DisplayException(__('server/plugins.errors.no_version'));
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
     * Name and icon of a project.
     *
     * @return array{id: string, name: string, icon: ?string, url: string}
     */
    public function project(string $project): array
    {
        $this->assertProject($project);
        $data = $this->get(self::API . '/project/' . rawurlencode($project), [], 60);

        return [
            'id' => (string) ($data['id'] ?? $project),
            'name' => (string) ($data['title'] ?? $project),
            'icon' => $data['icon_url'] ?? null,
            'url' => 'https://modrinth.com/plugin/' . rawurlencode((string) ($data['slug'] ?? $project)),
        ];
    }

    public function downloadHosts(): array
    {
        return ['cdn.modrinth.com'];
    }

    public function iconHosts(): array
    {
        return ['cdn.modrinth.com'];
    }

    private function assertProject(string $project): void
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $project)) {
            throw new DisplayException(__('server/plugins.errors.not_found'));
        }
    }

    /**
     * Release versions of Minecraft, newest first.
     *
     * @return string[]
     */
    public function gameVersions(): array
    {
        return Cache::remember('hydra:plugins:modrinth:game_versions', now()->addHours(6), function () {
            $tags = $this->get(self::API . '/tag/game_version', [], 360);

            return array_values(array_map(
                fn (array $tag) => (string) $tag['version'],
                array_filter($tags, fn ($tag) => is_array($tag) && ($tag['version_type'] ?? '') === 'release')
            ));
        });
    }
}
