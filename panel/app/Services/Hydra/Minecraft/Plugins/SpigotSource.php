<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Plugins;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: plugins of spigotmc.org through the Spiget API (https://spiget.org).
 * SpigotMC itself has no API and sits behind a bot protection; Spiget mirrors
 * the free resources (only their newest version) on cdn.spiget.org. Premium
 * resources and those hosted elsewhere can only be opened on their page.
 * Spigot only knows rough tested versions ("1.21"), so searches are not
 * filtered by the Minecraft version.
 */
class SpigotSource extends PluginSource
{
    public const API = 'https://api.spiget.org/v2';

    private const FIELDS = 'id,name,tag,downloads,premium,external,file,testedVersions,icon,author,updateDate,version';

    private const SORTS = ['relevance' => '-downloads', 'downloads' => '-downloads', 'updated' => '-updateDate', 'newest' => '-releaseDate'];

    public function key(): string
    {
        return 'spigot';
    }

    public function supports(string $software): bool
    {
        return in_array($software, ['paper', 'purpur', 'folia', 'spigot', 'bukkit'], true);
    }

    public function search(string $query, string $software, ?string $version, string $sort, int $page, int $perPage): array
    {
        $params = ['size' => $perPage, 'page' => $page, 'sort' => self::SORTS[$sort] ?? '-downloads', 'fields' => self::FIELDS];
        $url = trim($query) === ''
            ? self::API . '/resources/free'
            : self::API . '/search/resources/' . rawurlencode(mb_substr(trim($query), 0, 64));
        if (trim($query) !== '') {
            $params['field'] = 'name';
        }

        try {
            $data = $this->get($url, $params);
        } catch (DisplayException $exception) {
            // Spiget answers a search without hits with 404.
            if ($exception->getMessage() === __('server/plugins.errors.not_found')) {
                return ['data' => [], 'total' => 0];
            }
            throw $exception;
        }

        // Spigot only lists rough tested versions ("1.21"), often not the newest
        // one, so results are not filtered by version; the versions dialog
        // shows what was tested.
        $resources = array_values(array_filter(is_array($data) ? $data : [], fn ($resource) => is_array($resource) && isset($resource['id'])));

        return [
            'data' => array_map(fn (array $resource) => $this->project($resource), $resources),
            // Spiget does not tell the total: one more page as long as pages are full.
            'total' => ($page - 1) * $perPage + count(is_array($data) ? $data : []) + (count(is_array($data) ? $data : []) >= $perPage ? $perPage : 0),
        ];
    }

    public function versions(string $project, string $software, ?string $version): array
    {
        $resource = $this->resource($project);
        $latest = $this->get(self::API . '/resources/' . $resource['id'] . '/versions/latest', [], 30);
        $reason = $this->reason($resource);

        return [[
            'id' => (string) ($latest['id'] ?? $resource['version']['id'] ?? 'latest'),
            'name' => (string) ($latest['name'] ?? 'latest'),
            'channel' => 'release',
            'date' => isset($latest['releaseDate']) ? date(DATE_ATOM, (int) $latest['releaseDate']) : null,
            'downloads' => (int) ($latest['downloads'] ?? 0),
            'size' => $this->size($resource['file'] ?? []),
            'file' => '',
            'game_versions' => array_values(array_slice(array_reverse($resource['testedVersions'] ?? []), 0, 6)),
            'installable' => is_null($reason),
            'external_url' => $reason === 'external' ? ($resource['file']['externalUrl'] ?? null) : null,
            'url' => '',
        ]];
    }

    public function download(string $project, ?string $versionId, string $software, ?string $version): array
    {
        $resource = $this->resource($project);
        $reason = $this->reason($resource);
        if ($reason === 'premium') {
            throw new DisplayException(__('server/plugins.errors.premium'));
        }
        if ($reason === 'external') {
            throw new DisplayException(__('server/plugins.errors.external'));
        }
        $latest = $this->versions($project, $software, $version)[0];
        // Only the newest version is mirrored.
        if (!is_null($versionId) && $versionId !== $latest['id']) {
            throw new DisplayException(__('server/plugins.errors.no_version'));
        }

        return [
            'url' => 'https://cdn.spiget.org/file/spiget-resources/' . $resource['id'] . '.jar',
            'file' => self::fileName($resource['name'] . '-' . $latest['name'], 'spigot-' . $resource['id']),
            'version_id' => $latest['id'],
            'version_name' => $latest['name'],
            'project_id' => (string) $resource['id'],
            'name' => (string) $resource['name'],
            'icon' => $this->iconUrl($resource),
            'url_page' => 'https://www.spigotmc.org/resources/' . $resource['id'] . '/',
        ];
    }

    public function downloadHosts(): array
    {
        return ['cdn.spiget.org'];
    }

    public function iconHosts(): array
    {
        return ['api.spiget.org'];
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(string $project): array
    {
        if (!preg_match('/^\d{1,9}$/', $project)) {
            throw new DisplayException(__('server/plugins.errors.not_found'));
        }

        return $this->get(self::API . '/resources/' . $project, ['fields' => self::FIELDS], 30);
    }

    private function project(array $resource): array
    {
        $reason = $this->reason($resource);

        return [
            'id' => (string) $resource['id'],
            'slug' => (string) $resource['id'],
            'name' => html_entity_decode((string) ($resource['name'] ?? ''), ENT_QUOTES),
            'author' => '',
            'description' => html_entity_decode((string) ($resource['tag'] ?? ''), ENT_QUOTES),
            'icon' => $this->iconUrl($resource),
            'downloads' => (int) ($resource['downloads'] ?? 0),
            'updated' => isset($resource['updateDate']) ? date(DATE_ATOM, (int) $resource['updateDate']) : null,
            'url' => 'https://www.spigotmc.org/resources/' . $resource['id'] . '/',
            'installable' => is_null($reason),
            'reason' => $reason,
        ];
    }

    private function reason(array $resource): ?string
    {
        if (!empty($resource['premium'])) {
            return 'premium';
        }
        if (!empty($resource['external']) || ($resource['file']['type'] ?? '.jar') !== '.jar') {
            return 'external';
        }

        return null;
    }

    private function iconUrl(array $resource): ?string
    {
        // Resources without an own icon have an empty URL.
        return !empty($resource['icon']['url']) ? self::API . '/resources/' . $resource['id'] . '/icon/data' : null;
    }

    private function size(array $file): int
    {
        $size = (float) ($file['size'] ?? 0);

        return (int) match (strtoupper((string) ($file['sizeUnit'] ?? ''))) {
            'KB' => $size * 1024,
            'MB' => $size * 1024 * 1024,
            'GB' => $size * 1024 * 1024 * 1024,
            default => $size,
        };
    }
}
