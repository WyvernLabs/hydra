<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Modpacks;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource;

/**
 * Hydra: modpacks of modrinth.com (.mrpack, https://docs.modrinth.com/docs/modpacks/format/).
 * Only packs that run on a server are listed.
 */
class ModrinthPackSource extends ModpackSource
{
    public const API = 'https://api.modrinth.com/v2';

    private const SORTS = ['relevance' => 'relevance', 'downloads' => 'downloads', 'updated' => 'updated', 'newest' => 'newest'];

    public function key(): string
    {
        return 'modrinth';
    }

    public function search(string $query, string $loader, ?string $version, ?string $category, string $sort, int $page, int $perPage): array
    {
        $facets = [['project_types:modpack'], ['server_side:required', 'server_side:optional']];
        if ($loader !== '') {
            $facets[] = ['categories:' . $loader];
        }
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
                'url' => 'https://modrinth.com/modpack/' . rawurlencode((string) ($hit['slug'] ?? $hit['project_id'])),
                'categories' => array_values(array_intersect($hit['display_categories'] ?? $hit['categories'] ?? [], $known)),
                'loaders' => array_values(array_intersect($hit['categories'] ?? [], self::LOADERS)),
                'game_version' => $hit['latest_version'] ?? (is_array($hit['versions'] ?? null) ? end($hit['versions']) : null) ?: null,
                'installable' => true,
                'reason' => null,
            ], $data['hits'] ?? []),
            'total' => (int) ($data['total_hits'] ?? 0),
        ];
    }

    public function versions(string $project, string $loader, ?string $version): array
    {
        $this->assertProject($project);
        $query = [];
        if ($loader !== '') {
            $query['loaders'] = json_encode([$loader]);
        }
        if ($version) {
            $query['game_versions'] = json_encode([$version]);
        }

        $versions = $this->get(self::API . '/project/' . rawurlencode($project) . '/version', $query, 5);

        return array_values(array_filter(array_map(function ($entry) {
            if (!is_array($entry)) {
                return null;
            }
            $file = collect($entry['files'] ?? [])->first(fn ($file) => str_ends_with(strtolower((string) ($file['filename'] ?? '')), '.mrpack'));
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
                'loaders' => array_values(array_intersect($entry['loaders'] ?? [], self::LOADERS)),
                'installable' => true,
                'external_url' => null,
                'url' => (string) ($file['url'] ?? ''),
            ];
        }, is_array($versions) ? $versions : [])));
    }

    public function download(string $project, ?string $versionId, string $loader, ?string $version): array
    {
        $entry = self::pick($this->versions($project, $loader, $version), $versionId);
        if (!$entry || $entry['url'] === '') {
            throw new DisplayException(__('server/modpacks.errors.no_version'));
        }
        $this->assertProject($project);
        $data = $this->get(self::API . '/project/' . rawurlencode($project), [], 60);

        return [
            'url' => $entry['url'],
            'file' => 'pack.mrpack',
            'version_id' => $entry['id'],
            'version_name' => $entry['name'],
            'project_id' => (string) ($data['id'] ?? $project),
            'name' => (string) ($data['title'] ?? $project),
            'icon' => ($data['icon_url'] ?? null) ?: null,
            'url_page' => 'https://modrinth.com/modpack/' . rawurlencode((string) ($data['slug'] ?? $project)),
        ];
    }

    public function plan(string $archive): array
    {
        $index = self::readJson($archive, 'modrinth.index.json');
        if (($index['game'] ?? 'minecraft') !== 'minecraft' || !isset($index['dependencies']['minecraft'])) {
            throw new DisplayException(__('server/modpacks.errors.invalid_pack'));
        }

        $loader = $loaderVersion = null;
        foreach ($index['dependencies'] as $name => $value) {
            [$found, $foundVersion] = self::loader((string) $name, (string) $value);
            if ($found) {
                [$loader, $loaderVersion] = [$found, $foundVersion];
            }
        }

        $files = $skipped = [];
        foreach ($index['files'] ?? [] as $file) {
            $path = is_array($file) ? self::safePath((string) ($file['path'] ?? '')) : null;
            if (!$path) {
                continue;
            }
            if (($file['env']['server'] ?? 'required') === 'unsupported') {
                $skipped[] = basename($path);
                continue;
            }
            $url = collect($file['downloads'] ?? [])->first(function ($url) {
                $parts = is_string($url) ? parse_url($url) : [];

                return ($parts['scheme'] ?? '') === 'https' && in_array(strtolower($parts['host'] ?? ''), $this->fileHosts(), true);
            });
            if (!$url) {
                $skipped[] = basename($path);
                continue;
            }
            $files[] = ['path' => $path, 'url' => $url, 'size' => (int) ($file['fileSize'] ?? 0), 'sha1' => $file['hashes']['sha1'] ?? null, 'record' => null];
        }

        $this->addRecords($files);

        return [
            'minecraft' => (string) $index['dependencies']['minecraft'],
            'loader' => $loader,
            'loader_version' => $loaderVersion,
            'overrides' => ['overrides', 'server-overrides'],
            'files' => array_map(fn (array $file) => collect($file)->except('sha1')->all(), $files),
            'skipped' => $skipped,
        ];
    }

    /**
     * Finds the Modrinth projects of the mods by their hashes, so the mod
     * manager can update them later.
     */
    private function addRecords(array &$files): void
    {
        $hashes = array_values(array_filter(array_map(
            fn (array $file) => str_starts_with($file['path'], 'mods/') && is_string($file['sha1']) ? $file['sha1'] : null,
            $files
        )));
        if (empty($hashes)) {
            return;
        }

        try {
            $versions = Http::withHeaders(['User-Agent' => PluginSource::USER_AGENT])->timeout(30)
                ->post(self::API . '/version_files', ['hashes' => $hashes, 'algorithm' => 'sha1'])->json();
            $ids = array_values(array_unique(array_filter(array_map(fn ($version) => $version['project_id'] ?? null, is_array($versions) ? $versions : []))));
            $projects = [];
            foreach (array_chunk($ids, 100) as $chunk) {
                $answer = Http::withHeaders(['User-Agent' => PluginSource::USER_AGENT])->timeout(30)
                    ->get(self::API . '/projects', ['ids' => json_encode($chunk)])->json();
                foreach (is_array($answer) ? $answer : [] as $project) {
                    if (isset($project['id'])) {
                        $projects[$project['id']] = $project;
                    }
                }
            }
        } catch (\Throwable) {
            // Without records the mods work all the same, they only show as added by hand.
            return;
        }

        foreach ($files as &$file) {
            $version = is_string($file['sha1']) ? ($versions[$file['sha1']] ?? null) : null;
            $project = $version ? ($projects[$version['project_id'] ?? ''] ?? null) : null;
            if ($version && $project) {
                $file['record'] = [
                    'source' => 'modrinth',
                    'project_id' => (string) $project['id'],
                    'version_id' => (string) $version['id'],
                    'version_name' => (string) ($version['version_number'] ?? $version['id']),
                    'name' => (string) ($project['title'] ?? $project['id']),
                    'icon' => ($project['icon_url'] ?? null) ?: null,
                    'url' => 'https://modrinth.com/mod/' . rawurlencode((string) ($project['slug'] ?? $project['id'])),
                ];
            }
        }
    }

    public function categories(): array
    {
        return Cache::remember('hydra:modpacks:modrinth:categories', now()->addHours(6), function () {
            $tags = $this->get(self::API . '/tag/category', [], 360);
            $list = array_values(array_map(
                fn (array $tag) => ['key' => (string) $tag['name'], 'slug' => (string) $tag['name'], 'name' => (string) $tag['name'], 'parent' => null],
                array_filter($tags, fn ($tag) => is_array($tag) && ($tag['project_type'] ?? '') === 'modpack' && ($tag['header'] ?? '') === 'categories')
            ));
            usort($list, fn (array $a, array $b) => strcmp($a['key'], $b['key']));

            return $list;
        });
    }

    public function downloadHosts(): array
    {
        return ['cdn.modrinth.com'];
    }

    public function fileHosts(): array
    {
        // The hosts the .mrpack format allows.
        return ['cdn.modrinth.com', 'github.com', 'raw.githubusercontent.com', 'gitlab.com'];
    }

    public function iconHosts(): array
    {
        return ['cdn.modrinth.com'];
    }
}
