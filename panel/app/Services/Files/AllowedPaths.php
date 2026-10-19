<?php

namespace Pterodactyl\Services\Files;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;

/**
 * Hydra: the folders a subuser may work in. A subuser without folders may use
 * the whole server, the same as the owner.
 *
 * Paths are compared after resolving "." and ".." the same way Wings does, so
 * "/world/../plugins" counts as "/plugins". The folders above an allowed one
 * can be opened, but only show the way to the allowed folders.
 */
class AllowedPaths
{
    /**
     * @param string[] $paths normalized paths, empty for no restriction
     */
    public function __construct(private array $paths = [])
    {
    }

    /**
     * The folders the user may access on the server.
     */
    public static function for(Server $server, User $user): self
    {
        if ($user->id === $server->owner_id || $user->canAccessAllServers()) {
            return new self();
        }

        $subuser = $server->subusers->firstWhere('user_id', $user->id);

        return new self(self::clean($subuser?->allowed_paths ?? []));
    }

    /**
     * Turns a path into the form "/a/b", resolving "." and "..".
     */
    public static function normalize(?string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path ?? '')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        return '/' . implode('/', $parts);
    }

    /**
     * Normalizes a list of folders and drops the ones inside another folder of
     * the list. Allowing "/" is the same as no restriction at all.
     *
     * @return string[]
     */
    public static function clean(array $paths): array
    {
        $paths = array_filter($paths, fn ($path) => is_string($path) && trim($path) !== '');
        $paths = array_unique(array_map(fn (string $path) => self::normalize(trim($path)), $paths));
        if (in_array('/', $paths, true)) {
            return [];
        }

        sort($paths);

        $cleaned = [];
        foreach ($paths as $path) {
            $covered = false;
            foreach ($cleaned as $kept) {
                $covered = $covered || $path === $kept || str_starts_with($path, $kept . '/');
            }

            if (!$covered) {
                $cleaned[] = $path;
            }
        }

        return $cleaned;
    }

    public function isRestricted(): bool
    {
        return count($this->paths) > 0;
    }

    /**
     * @return string[]
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Whether the path is one of the allowed folders or inside one of them.
     */
    public function allows(?string $path): bool
    {
        if (!$this->isRestricted()) {
            return true;
        }

        $path = self::normalize($path);
        foreach ($this->paths as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the folder may be opened: an allowed folder, something inside
     * one, or a folder on the way to one.
     */
    public function canBrowse(?string $path): bool
    {
        if ($this->allows($path)) {
            return true;
        }

        $path = self::normalize($path);
        foreach ($this->paths as $allowed) {
            if ($path === '/' || str_starts_with($allowed, $path . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every folder of the other list lies within this one, which is
     * needed before a subuser may hand out folders to someone else.
     */
    public function covers(self $other): bool
    {
        if (!$this->isRestricted()) {
            return true;
        }

        if (!$other->isRestricted()) {
            return false;
        }

        foreach ($other->paths() as $path) {
            if (!$this->allows($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Keeps only the entries of a directory listing the user may see.
     */
    public function filterListing(?string $directory, array $entries): array
    {
        if (!$this->isRestricted()) {
            return $entries;
        }

        $directory = self::normalize($directory);

        return array_values(array_filter($entries, function ($entry) use ($directory) {
            $path = rtrim($directory, '/') . '/' . ($entry['name'] ?? '');

            return $this->canBrowse($path);
        }));
    }

    /**
     * Stops the request unless every path is allowed.
     *
     * @throws HttpForbiddenException
     */
    public function assertAllowed(?string ...$paths): void
    {
        foreach ($paths as $path) {
            if (!$this->allows($path)) {
                throw new HttpForbiddenException(__('server/files.restricted', ['path' => self::normalize($path)]));
            }
        }
    }

    /**
     * Stops the request unless the folder may be opened.
     *
     * @throws HttpForbiddenException
     */
    public function assertBrowsable(?string $path): void
    {
        if (!$this->canBrowse($path)) {
            throw new HttpForbiddenException(__('server/files.restricted', ['path' => self::normalize($path)]));
        }
    }

    /**
     * Joins a folder and a file name the way Wings does.
     */
    public static function join(?string $root, ?string $name): string
    {
        return self::normalize(($root ?? '/') . '/' . ($name ?? ''));
    }
}
