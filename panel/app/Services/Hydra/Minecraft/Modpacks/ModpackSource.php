<?php

namespace Pterodactyl\Services\Hydra\Minecraft\Modpacks;

use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Files\AllowedPaths;
use Pterodactyl\Services\Hydra\Minecraft\Mods\ModSource;

/**
 * Hydra: a place modpacks come from (addon "modpacks"): Modrinth or
 * CurseForge. Search, versions and categories answer in the shapes of
 * ModSource (loader "" means every loader); download() returns the pack
 * archive and plan() turns a downloaded archive into what gets installed.
 *
 * Plan: minecraft, loader (fabric / forge / neoforge / quilt / null for
 * vanilla), loader_version, overrides (folders of the archive copied into the
 * server, in order), files (path, url, size, record: source, project_id,
 * version_id, version_name, name, icon, url or null), skipped (names of files
 * that cannot be installed on a server).
 */
abstract class ModpackSource extends ModSource
{
    // Largest pack archive the panel reads, in bytes.
    public const ARCHIVE_LIMIT = 524288000;

    /**
     * Reads a downloaded pack archive and finds out what to install.
     *
     * @throws DisplayException when the archive is no pack of this source
     */
    abstract public function plan(string $archive): array;

    /**
     * Hosts the files listed in packs may be downloaded from.
     *
     * @return string[]
     */
    abstract public function fileHosts(): array;

    /**
     * A path inside the server, relative, without "..", or null when unsafe.
     */
    public static function safePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
            return null;
        }
        $normalized = ltrim(AllowedPaths::normalize('/' . $path), '/');

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Opens the archive and returns the decoded JSON file in its root.
     */
    protected static function readJson(string $archive, string $name): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new DisplayException(__('server/modpacks.errors.invalid_pack'));
        }
        $content = $zip->getFromName($name);
        $zip->close();
        $data = is_string($content) ? json_decode($content, true) : null;
        if (!is_array($data)) {
            throw new DisplayException(__('server/modpacks.errors.invalid_pack'));
        }

        return $data;
    }

    /**
     * Loader and its version from a key like "neoforge", "fabric-loader" or
     * an id like "forge-47.2.0".
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected static function loader(string $name, ?string $version = null): array
    {
        $name = strtolower($name);
        if (is_null($version) && preg_match('/^([a-z]+)-(.+)$/', $name, $match)) {
            [$name, $version] = [$match[1], $match[2]];
        }
        $map = ['forge' => 'forge', 'neoforge' => 'neoforge', 'fabric' => 'fabric', 'fabric-loader' => 'fabric', 'quilt' => 'quilt', 'quilt-loader' => 'quilt'];

        return [$map[$name] ?? null, $version];
    }
}
