<?php

namespace Pterodactyl\Services\Hydra\Minecraft;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: server software, versions and builds of Minecraft from mcjars.app
 * (addon "versions"). Answers are cached, since they rarely change and every
 * user of the panel asks for the same.
 */
class McJarsClient
{
    public const API = 'https://mcjars.app/api';

    // Software types are written like "PAPER" or "VELOCITY_CTD".
    public const TYPE_PATTERN = '/^[A-Z0-9_]{2,40}$/';

    /**
     * Software types grouped by category ("recommended", "established", …).
     *
     * @return array<string, array<string, array>>
     */
    public function types(): array
    {
        return Cache::remember('hydra:mcjars:types', now()->addHour(), fn () => $this->get('/v2/types')['types'] ?? []);
    }

    /**
     * A single software type, null when mcjars does not know it.
     */
    public function type(string $type): ?array
    {
        foreach ($this->types() as $types) {
            if (isset($types[$type])) {
                return $types[$type];
            }
        }

        return null;
    }

    /**
     * Versions of a type, keyed by version, oldest first.
     *
     * @return array<string, array>
     */
    public function versions(string $type): array
    {
        $this->assertType($type);

        return Cache::remember(
            "hydra:mcjars:versions:$type",
            now()->addMinutes(30),
            fn () => $this->get("/v2/builds/$type")['builds'] ?? []
        );
    }

    /**
     * Builds of a version, newest first.
     */
    public function builds(string $type, string $version): array
    {
        $this->assertType($type);
        if (!array_key_exists($version, $this->versions($type))) {
            throw new DisplayException(__('server/versions.errors.unknown_version'));
        }

        return Cache::remember(
            'hydra:mcjars:builds:' . $type . ':' . sha1($version),
            now()->addMinutes(15),
            fn () => $this->get("/v2/builds/$type/" . rawurlencode($version))['builds'] ?? []
        );
    }

    /**
     * A single build with its installation steps.
     */
    public function build(int $id): array
    {
        $build = $this->get("/v1/build/$id")['build'] ?? null;
        if (!is_array($build)) {
            throw new DisplayException(__('server/versions.errors.unknown_build'));
        }

        return $build;
    }

    /**
     * Icon of a type as PNG bytes, so browsers do not load it from mcjars.
     */
    public function icon(string $type): ?string
    {
        $url = Arr::get($this->type($type) ?? [], 'icon');
        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            return null;
        }

        // An empty string marks an icon that could not be loaded.
        $encoded = Cache::remember("hydra:mcjars:icon:$type", now()->addDay(), function () use ($url) {
            try {
                $response = Http::timeout(10)->get($url);
            } catch (ConnectionException) {
                return '';
            }

            return $response->successful() && strlen($response->body()) < 2 * 1024 * 1024 ? base64_encode($response->body()) : '';
        });

        return $encoded ? base64_decode($encoded) : null;
    }

    private function assertType(string $type): void
    {
        if (!preg_match(self::TYPE_PATTERN, $type) || is_null($this->type($type))) {
            throw new DisplayException(__('server/versions.errors.unknown_type'));
        }
    }

    /**
     * @throws DisplayException
     */
    private function get(string $path): array
    {
        try {
            $response = Http::timeout(20)->acceptJson()->get(self::API . $path);
        } catch (ConnectionException) {
            throw new DisplayException(__('server/versions.errors.unreachable'));
        }

        $data = $response->json();
        if (!$response->successful() || !is_array($data) || !($data['success'] ?? false)) {
            throw new DisplayException(__('server/versions.errors.unreachable'));
        }

        return $data;
    }
}
