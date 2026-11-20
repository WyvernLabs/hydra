<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Mods;

use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: mods of modrinth.com (https://docs.modrinth.com/api/). Only mods that
 * run on a server (server_side required or optional) are listed.
 */
class ModrinthModSource extends ModSource
{
    public const API = 'https://api.modrinth.com/v2';

    private const SORTS = ['relevance' => 'relevance', 'downloads' => 'downloads', 'updated' => 'updated', 'newest' => 'newest'];

    public function key(): string
    {
        return 'modrinth';
    }

    /**
     * Loaders whose mods run on the loader: Quilt also loads Fabric mods.
     *
     * @return string[]
     */
    private static function loaders(string $loader): array
    {
        return $loader === 'quilt' ? ['quilt', 'fabric'] : [$loader];
    }

    public function search(string $query, string $loader, ?string $version, ?string $category, string $sort, int $page, int $perPage): array
    {
        $facets = [
            ['project_types:mod'],
            array_map(fn (string $name) => 'categories:' . $name, self::loaders($loader)),
            ['server_side:required', 'server_side:optional'],
        ];
        if ($version) {
            $facets[] = ['versions:' . $version];
        }
        if ($category) {
            $facets[] = ['categories:' . $category];
        }

        $data = $this->get(self::API . '/search', [
            'query' => $query,
            'facets' => json_encode($facets),
            'index' => self::SORTS[$sort] ?? 'relevance',
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]);

        $known = array_column($this->categories(), 'key');

        return [
            'data' => array_map(fn (array $hit) => [
                'id' => (string) $hit['project_id'],
                'slug' => (string) ($hit['slug'] ?? $hit['project_id']),
                'name' => (string) ($hit['title'] ?? $hit['slug'] ?? ''),
                'author' => (string) ($hit['author'] ?? ''),
                'description' => (string) ($hit['description'] ?? ''),
                'icon' => ($hit['icon_url'] ?? null) ?: null,
                'downloads' => (int) ($hit['downloads'] ?? 0),
                'updated' => $hit['date_modified'] ?? null,
                'url' => 'https://modrinth.com/mod/' . rawurlencode((string) ($hit['slug'] ?? $hit['project_id'])),
                'categories' => array_values(array_intersect($hit['display_categories'] ?? $hit['categories'] ?? [], $known)),
                'installable' => true,
                'reason' => null,
            ], $data['hits'] ?? []),
            'total' => (int) ($data['total_hits'] ?? 0),
        ];
    }

    public function versions(string $project, string $loader, ?string $version): array
    {
        $this->assertProject($project);
        $query = ['loaders' => json_encode(self::loaders($loader))];
        if ($version) {
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
                'dependencies' => array_values(array_unique(array_filter(array_map(
                    fn ($dependency) => is_array($dependency) && ($dependency['dependency_type'] ?? '') === 'required' && !empty($dependency['project_id'])
                        ? (string) $dependency['project_id']
                        : null,
                    $entry['dependencies'] ?? []
                )))),
            ];
        }, is_array($versions) ? $versions : [])));
    }

    public function download(string $project, ?string $versionId, string $loader, ?string $version): array
    {
        $entry = self::pick($this->versions($project, $loader, $version), $versionId);
        if (!$entry || $entry['url'] === '') {
            throw new DisplayException(__('server/mods.errors.no_version'));
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
            'dependencies' => $entry['dependencies'],
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
            'icon' => ($data['icon_url'] ?? null) ?: null,
            'url' => 'https://modrinth.com/mod/' . rawurlencode((string) ($data['slug'] ?? $project)),
        ];
    }

    public function categories(): array
    {
        return Cache::remember('hydra:mods:modrinth:categories', now()->addHours(6), function () {
            $tags = $this->get(self::API . '/tag/category', [], 360);

            $list = array_values(array_map(
                fn (array $tag) => ['key' => (string) $tag['name'], 'slug' => (string) $tag['name'], 'name' => (string) $tag['name'], 'parent' => null],
                array_filter($tags, fn ($tag) => is_array($tag) && ($tag['project_type'] ?? '') === 'mod' && ($tag['header'] ?? '') === 'categories')
            ));
            usort($list, fn (array $a, array $b) => strcmp($a['key'], $b['key']));

            return $list;
        });
    }

    public function downloadHosts(): array
    {
        return ['cdn.modrinth.com'];
    }

    public function iconHosts(): array
    {
        return ['cdn.modrinth.com'];
    }

    /**
     * Release versions of Minecraft, newest first.
     *
     * @return string[]
     */
    public function gameVersions(): array
    {
        return Cache::remember('hydra:mods:modrinth:game_versions', now()->addHours(6), function () {
            $tags = $this->get(self::API . '/tag/game_version', [], 360);

            return array_values(array_map(
                fn (array $tag) => (string) $tag['version'],
                array_filter($tags, fn ($tag) => is_array($tag) && ($tag['version_type'] ?? '') === 'release')
            ));
        });
    }
}
