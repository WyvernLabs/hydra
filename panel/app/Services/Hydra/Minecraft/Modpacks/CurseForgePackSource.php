<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Modpacks;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\ModpacksAddon;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource;

/**
 * Hydra: modpacks of curseforge.com (pack zip with a manifest.json). Mods
 * whose authors do not allow downloads outside of CurseForge cannot be
 * installed automatically and are listed for the user; mods only meant for
 * the client are left out.
 */
class CurseForgePackSource extends ModpackSource
{
    public const API = 'https://api.curseforge.com/v1';

    public const GAME = 432;

    public const CLASS_MODPACKS = 4471;

    public const CLASS_MODS = 6;

    private const LOADER_TYPES = ['forge' => 1, 'fabric' => 4, 'quilt' => 5, 'neoforge' => 6];

    private const SORTS = ['relevance' => 1, 'downloads' => 6, 'updated' => 3, 'newest' => 11];

    private const MAX_RESULTS = 10000;

    public function __construct(private ModpacksAddon $addon)
    {
    }

    public function key(): string
    {
        return 'curseforge';
    }

    protected function headers(): array
    {
        return ['x-api-key' => $this->addon->curseForgeKey()];
    }

    public function search(string $query, string $loader, ?string $version, ?string $category, string $sort, int $page, int $perPage): array
    {
        $index = ($page - 1) * $perPage;
        if ($index + $perPage > self::MAX_RESULTS) {
            return ['data' => [], 'total' => 0];
        }

        $data = $this->get(self::API . '/mods/search', array_filter([
            'gameId' => self::GAME,
            'classId' => self::CLASS_MODPACKS,
            'searchFilter' => $query !== '' ? $query : null,
            'categoryId' => $category && ctype_digit($category) ? (int) $category : null,
            'gameVersion' => $version,
            'modLoaderType' => self::LOADER_TYPES[$loader] ?? null,
            'sortField' => self::SORTS[$sort] ?? 1,
            'sortOrder' => 'desc',
            'index' => $index,
            'pageSize' => $perPage,
        ], fn ($value) => !is_null($value)), 10, 'server/mods.errors.search_forbidden');

        return [
            'data' => array_map(fn (array $mod) => $this->project($mod), array_filter($data['data'] ?? [], 'is_array')),
            'total' => min((int) ($data['pagination']['totalCount'] ?? 0), self::MAX_RESULTS),
        ];
    }

    public function versions(string $project, string $loader, ?string $version): array
    {
        $this->assertProject($project);
        $mod = $this->mod($project);
        $files = $this->get(self::API . '/mods/' . $project . '/files', array_filter([
            'gameVersion' => $version,
            'modLoaderType' => self::LOADER_TYPES[$loader] ?? null,
            'pageSize' => 50,
        ], fn ($value) => !is_null($value)), 5);

        $page = $this->page($mod);
        $list = array_values(array_filter(array_map(function ($file) use ($page) {
            if (!is_array($file) || empty($file['id']) || ($file['isAvailable'] ?? true) === false || !empty($file['isServerPack'])) {
                return null;
            }
            $url = (string) ($file['downloadUrl'] ?? '');
            $names = array_filter($file['gameVersions'] ?? [], 'is_string');

            return [
                'id' => (string) $file['id'],
                'name' => (string) ($file['displayName'] ?? $file['fileName'] ?? $file['id']),
                'channel' => [2 => 'beta', 3 => 'alpha'][(int) ($file['releaseType'] ?? 1)] ?? 'release',
                'date' => $file['fileDate'] ?? null,
                'downloads' => (int) ($file['downloadCount'] ?? 0),
                'size' => (int) ($file['fileLength'] ?? 0),
                'file' => (string) ($file['fileName'] ?? ''),
                'game_versions' => array_values(array_slice(array_reverse(array_filter($names, fn ($name) => preg_match('/^\d+\.\d+(\.\d+)?$/', $name))), 0, 6)),
                'loaders' => array_values(array_intersect(array_map('strtolower', $names), self::LOADERS)),
                'installable' => $url !== '',
                'external_url' => $url === '' ? $page . '/files/' . $file['id'] : null,
                'url' => $url,
            ];
        }, $files['data'] ?? [])));

        usort($list, fn (array $a, array $b) => strcmp((string) $b['date'], (string) $a['date']));

        return $list;
    }

    public function download(string $project, ?string $versionId, string $loader, ?string $version): array
    {
        $entry = self::pick($this->versions($project, $loader, $version), $versionId);
        if (!$entry) {
            throw new DisplayException(__('server/modpacks.errors.no_version'));
        }
        if (!$entry['installable']) {
            throw new DisplayException(__('server/mods.errors.external'));
        }
        $info = $this->project($this->mod($project));

        return [
            'url' => $entry['url'],
            'file' => 'pack.zip',
            'version_id' => $entry['id'],
            'version_name' => $entry['name'],
            'project_id' => $info['id'],
            'name' => $info['name'],
            'icon' => $info['icon'],
            'url_page' => $info['url'],
        ];
    }

    public function plan(string $archive): array
    {
        $manifest = self::readJson($archive, 'manifest.json');
        $minecraft = (string) ($manifest['minecraft']['version'] ?? '');
        if ($minecraft === '') {
            throw new DisplayException(__('server/modpacks.errors.invalid_pack'));
        }

        $loaders = collect($manifest['minecraft']['modLoaders'] ?? [])->filter(fn ($value) => is_array($value));
        $primary = $loaders->firstWhere('primary', true) ?? $loaders->first();
        [$loader, $loaderVersion] = $primary ? self::loader((string) ($primary['id'] ?? '')) : [null, null];

        $entries = collect($manifest['files'] ?? [])
            ->filter(fn ($file) => is_array($file) && !empty($file['fileID']) && !empty($file['projectID']) && ($file['required'] ?? true) !== false);
        $fileIds = $entries->pluck('fileID')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $modIds = $entries->pluck('projectID')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $fileInfo = collect();
        foreach (array_chunk($fileIds, 500) as $chunk) {
            $fileInfo = $fileInfo->merge($this->post('/mods/files', ['fileIds' => $chunk]));
        }
        $fileInfo = $fileInfo->filter(fn ($value) => is_array($value))->keyBy('id');
        $modInfo = collect();
        foreach (array_chunk($modIds, 500) as $chunk) {
            $modInfo = $modInfo->merge($this->post('/mods', ['modIds' => $chunk, 'filterPcOnly' => true]));
        }
        $modInfo = $modInfo->filter(fn ($value) => is_array($value))->keyBy('id');

        $files = $skipped = $manual = [];
        foreach ($entries as $entry) {
            $file = $fileInfo->get((int) $entry['fileID']);
            $mod = $modInfo->get((int) $entry['projectID']);
            $name = (string) ($mod['name'] ?? $file['displayName'] ?? $entry['projectID']);
            if (!$file) {
                $skipped[] = $name;
                continue;
            }
            // Resource packs, shaders and the like are for the client.
            if ($mod && (int) ($mod['classId'] ?? self::CLASS_MODS) !== self::CLASS_MODS) {
                $skipped[] = $name;
                continue;
            }
            $tags = array_map('strtolower', array_filter($file['gameVersions'] ?? [], 'is_string'));
            if (in_array('client', $tags, true) && !in_array('server', $tags, true)) {
                $skipped[] = $name;
                continue;
            }
            $url = (string) ($file['downloadUrl'] ?? '');
            $parts = parse_url($url);
            $fileName = self::safePath(basename((string) ($file['fileName'] ?? '')));
            if ($url === '' || ($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), $this->fileHosts(), true) || !$fileName) {
                $manual[] = ['name' => $name, 'url' => $mod ? $this->page($mod) . '/files/' . $file['id'] : null];
                continue;
            }
            $files[] = [
                'path' => 'mods/' . $fileName,
                'url' => $url,
                'size' => (int) ($file['fileLength'] ?? 0),
                'record' => [
                    'source' => 'curseforge',
                    'project_id' => (string) $entry['projectID'],
                    'version_id' => (string) $file['id'],
                    'version_name' => (string) ($file['displayName'] ?? $file['fileName'] ?? $file['id']),
                    'name' => $name,
                    'icon' => $mod ? (($mod['logo']['thumbnailUrl'] ?? null) ?: null) : null,
                    'url' => $mod ? $this->page($mod) : null,
                ],
            ];
        }

        return [
            'minecraft' => $minecraft,
            'loader' => $loader,
            'loader_version' => $loaderVersion,
            'overrides' => [is_string($manifest['overrides'] ?? null) && self::safePath($manifest['overrides']) ? self::safePath($manifest['overrides']) : 'overrides'],
            'files' => $files,
            'skipped' => $skipped,
            'manual' => $manual,
        ];
    }

    public function categories(): array
    {
        return Cache::remember('hydra:modpacks:curseforge:categories', now()->addHours(6), function () {
            $data = $this->get(self::API . '/categories', ['gameId' => self::GAME, 'classId' => self::CLASS_MODPACKS], 360);

            $list = [];
            foreach ($data['data'] ?? [] as $category) {
                if (!is_array($category) || empty($category['id']) || !empty($category['isClass'])) {
                    continue;
                }
                $parent = (int) ($category['parentCategoryId'] ?? self::CLASS_MODPACKS);
                $list[] = [
                    'key' => (string) $category['id'],
                    'slug' => (string) ($category['slug'] ?? ''),
                    'name' => (string) ($category['name'] ?? $category['id']),
                    'parent' => $parent === self::CLASS_MODPACKS || $parent === 0 ? null : (string) $parent,
                ];
            }
            usort($list, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

            return $list;
        });
    }

    public function downloadHosts(): array
    {
        return ['edge.forgecdn.net', 'mediafilez.forgecdn.net'];
    }

    public function fileHosts(): array
    {
        return ['edge.forgecdn.net', 'mediafilez.forgecdn.net'];
    }

    public function iconHosts(): array
    {
        return ['media.forgecdn.net'];
    }

    /**
     * POST request for many files or projects at once.
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = Http::withHeaders(array_merge(['User-Agent' => PluginSource::USER_AGENT, 'Accept' => 'application/json'], $this->headers()))
                ->timeout(30)
                ->post(self::API . $path, $body);
        } catch (ConnectionException) {
            throw new DisplayException(__('server/mods.errors.unreachable', ['source' => $this->name()]));
        }
        if (!$response->successful()) {
            throw new DisplayException(__('server/mods.errors.unreachable', ['source' => $this->name()]));
        }

        return array_values(array_filter($response->json('data') ?? [], 'is_array'));
    }

    private function mod(string $project): array
    {
        $data = $this->get(self::API . '/mods/' . $project, [], 60);

        return is_array($data['data'] ?? null) ? $data['data'] : throw new DisplayException(__('server/modpacks.errors.not_found'));
    }

    private function page(array $mod): string
    {
        $url = (string) ($mod['links']['websiteUrl'] ?? '');

        return str_starts_with($url, 'https://www.curseforge.com/')
            ? rtrim($url, '/')
            : 'https://www.curseforge.com/minecraft/' . ((int) ($mod['classId'] ?? self::CLASS_MODPACKS) === self::CLASS_MODPACKS ? 'modpacks' : 'mc-mods')
                . '/' . rawurlencode((string) ($mod['slug'] ?? $mod['id'] ?? ''));
    }

    private function project(array $mod): array
    {
        $versions = collect($mod['latestFilesIndexes'] ?? [])->pluck('gameVersion')->filter(fn ($value) => is_string($value));

        return [
            'id' => (string) $mod['id'],
            'slug' => (string) ($mod['slug'] ?? $mod['id']),
            'name' => (string) ($mod['name'] ?? $mod['slug'] ?? ''),
            'author' => (string) ($mod['authors'][0]['name'] ?? ''),
            'description' => (string) ($mod['summary'] ?? ''),
            'icon' => ($mod['logo']['thumbnailUrl'] ?? null) ?: (($mod['logo']['url'] ?? null) ?: null),
            'downloads' => (int) ($mod['downloadCount'] ?? 0),
            'updated' => $mod['dateModified'] ?? null,
            'url' => $this->page($mod),
            'categories' => array_values(array_map(fn ($category) => (string) ($category['id'] ?? ''), array_filter($mod['categories'] ?? [], 'is_array'))),
            'loaders' => array_values(array_unique(array_filter(array_map(
                fn ($index) => array_search((int) ($index['modLoader'] ?? 0), self::LOADER_TYPES, true) ?: null,
                array_filter($mod['latestFilesIndexes'] ?? [], 'is_array')
            )))),
            'game_version' => $versions->first(),
            'installable' => ($mod['allowModDistribution'] ?? null) !== false,
            'reason' => ($mod['allowModDistribution'] ?? null) === false ? 'external' : null,
        ];
    }

    protected function assertProject(string $project): void
    {
        if (!preg_match('/^\d{1,12}$/', $project)) {
            throw new DisplayException(__('server/modpacks.errors.not_found'));
        }
    }
}
