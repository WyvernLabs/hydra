<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Mods;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\ModsAddon;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource;

/**
 * Hydra: mods of curseforge.com through the CurseForge API for third parties
 * (https://docs.curseforge.com/rest-api/). Needs an API key from the
 * CurseForge for Studios console, set in the addon. Mods whose authors do not
 * allow downloads outside of CurseForge only get a link to their page.
 */
class CurseForgeSource extends ModSource
{
    public const API = 'https://api.curseforge.com/v1';

    public const GAME = 432;

    // Class "Mods" of the game Minecraft.
    public const CLASS_MODS = 6;

    // modLoaderType of the API. Quilt loads Fabric mods and hardly any mod on
    // CurseForge is tagged Quilt, so Quilt servers get Fabric mods.
    private const LOADER_TYPES = ['forge' => 1, 'fabric' => 4, 'quilt' => 4, 'neoforge' => 6];

    // sortField of the API: Featured, TotalDownloads, LastUpdated, ReleasedDate.
    private const SORTS = ['relevance' => 1, 'downloads' => 6, 'updated' => 3, 'newest' => 11];

    // The API refuses index + pageSize above this.
    private const MAX_RESULTS = 10000;

    public function __construct(private ModsAddon $addon)
    {
    }

    public function key(): string
    {
        return 'curseforge';
    }

    protected function headers(): array
    {
        return ['x-api-key' => (string) $this->addon->get('curseforge_key')];
    }

    public function search(string $query, string $loader, ?string $version, ?string $category, string $sort, int $page, int $perPage): array
    {
        $index = ($page - 1) * $perPage;
        if ($index + $perPage > self::MAX_RESULTS) {
            return ['data' => [], 'total' => 0];
        }

        $data = $this->get(self::API . '/mods/search', array_filter([
            'gameId' => self::GAME,
            'classId' => self::CLASS_MODS,
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
            if (!is_array($file) || empty($file['id']) || ($file['isAvailable'] ?? true) === false) {
                return null;
            }
            $url = (string) ($file['downloadUrl'] ?? '');

            return [
                'id' => (string) $file['id'],
                'name' => (string) ($file['displayName'] ?? $file['fileName'] ?? $file['id']),
                'channel' => [2 => 'beta', 3 => 'alpha'][(int) ($file['releaseType'] ?? 1)] ?? 'release',
                'date' => $file['fileDate'] ?? null,
                'downloads' => (int) ($file['downloadCount'] ?? 0),
                'size' => (int) ($file['fileLength'] ?? 0),
                'file' => (string) ($file['fileName'] ?? ''),
                'game_versions' => array_values(array_slice(array_reverse(array_filter(
                    $file['gameVersions'] ?? [],
                    fn ($name) => is_string($name) && preg_match('/^\d+\.\d+(\.\d+)?$/', $name)
                )), 0, 6)),
                'installable' => $url !== '',
                'external_url' => $url === '' ? $page . '/files/' . $file['id'] : null,
                'url' => $url,
                'dependencies' => array_values(array_unique(array_filter(array_map(
                    fn ($dependency) => is_array($dependency) && (int) ($dependency['relationType'] ?? 0) === 3 && !empty($dependency['modId'])
                        ? (string) $dependency['modId']
                        : null,
                    $file['dependencies'] ?? []
                )))),
            ];
        }, $files['data'] ?? [])));

        usort($list, fn (array $a, array $b) => strcmp((string) $b['date'], (string) $a['date']));

        return $list;
    }

    public function download(string $project, ?string $versionId, string $loader, ?string $version): array
    {
        $entry = self::pick($this->versions($project, $loader, $version), $versionId);
        if (!$entry) {
            throw new DisplayException(__('server/mods.errors.no_version'));
        }
        if (!$entry['installable']) {
            throw new DisplayException(__('server/mods.errors.external'));
        }
        $info = $this->project($this->mod($project));

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

    public function categories(): array
    {
        return Cache::remember('hydra:mods:curseforge:categories', now()->addHours(6), function () {
            $data = $this->get(self::API . '/categories', ['gameId' => self::GAME, 'classId' => self::CLASS_MODS], 360);

            $list = [];
            foreach ($data['data'] ?? [] as $category) {
                if (!is_array($category) || empty($category['id']) || !empty($category['isClass'])) {
                    continue;
                }
                $parent = (int) ($category['parentCategoryId'] ?? self::CLASS_MODS);
                $list[] = [
                    'key' => (string) $category['id'],
                    'slug' => (string) ($category['slug'] ?? ''),
                    'name' => (string) ($category['name'] ?? $category['id']),
                    'parent' => $parent === self::CLASS_MODS || $parent === 0 ? null : (string) $parent,
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

    public function iconHosts(): array
    {
        return ['media.forgecdn.net'];
    }

    /**
     * Asks CurseForge what a key may do. "status" is the answer to a request
     * that needs a valid key (game details), "search" the answer of the mod
     * search, which CurseForge refuses for keys without search access; 0 when
     * CurseForge cannot be reached. Refusals are logged without the key.
     *
     * @return array{status: int, search: int}
     */
    public static function checkKey(string $key): array
    {
        $ask = function (string $path, array $query) use ($key): int {
            try {
                $response = Http::withHeaders(['User-Agent' => PluginSource::USER_AGENT, 'Accept' => 'application/json', 'x-api-key' => $key])
                    ->timeout(8)
                    ->get(self::API . $path, $query);
            } catch (ConnectionException $exception) {
                Log::warning('Hydra mods: CurseForge unreachable while checking the API key', ['path' => $path, 'error' => $exception->getMessage()]);

                return 0;
            }
            if (!$response->successful()) {
                Log::warning('Hydra mods: CurseForge refused the API key check', [
                    'path' => $path,
                    'status' => $response->status(),
                    'key_length' => strlen($key),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);
            }

            return $response->status();
        };

        $status = $ask('/games/' . self::GAME, []);

        return [
            'status' => $status,
            'search' => $status >= 200 && $status < 300
                ? $ask('/mods/search', ['gameId' => self::GAME, 'classId' => self::CLASS_MODS, 'pageSize' => 1])
                : $status,
        ];
    }

    private function mod(string $project): array
    {
        $data = $this->get(self::API . '/mods/' . $project, [], 60);

        return is_array($data['data'] ?? null) ? $data['data'] : throw new DisplayException(__('server/mods.errors.not_found'));
    }

    private function page(array $mod): string
    {
        $url = (string) ($mod['links']['websiteUrl'] ?? '');

        return str_starts_with($url, 'https://www.curseforge.com/')
            ? rtrim($url, '/')
            : 'https://www.curseforge.com/minecraft/mc-mods/' . rawurlencode((string) ($mod['slug'] ?? $mod['id'] ?? ''));
    }

    private function project(array $mod): array
    {
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
            'installable' => ($mod['allowModDistribution'] ?? null) !== false,
            'reason' => ($mod['allowModDistribution'] ?? null) === false ? 'external' : null,
        ];
    }

    protected function assertProject(string $project): void
    {
        if (!preg_match('/^\d{1,12}$/', $project)) {
            throw new DisplayException(__('server/mods.errors.not_found'));
        }
    }
}
