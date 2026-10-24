<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Addons\VersionsAddon;
use Pterodactyl\Services\Hydra\Minecraft\McJarsClient;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Hydra\Minecraft\VersionInstallService;

/**
 * Hydra: Minecraft version changer (addon "versions"). Lists the software,
 * versions and builds of mcjars.app and installs a build on the server.
 */
class MinecraftVersionController extends ClientApiController
{
    public function __construct(
        private VersionsAddon $addon,
        private McJarsClient $client,
        private VersionInstallService $service,
    ) {
        parent::__construct();
    }

    /**
     * Software types by category and what is installed on the server.
     */
    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server);

        $categories = [];
        foreach ($this->client->types() as $category => $types) {
            $categories[] = [
                'id' => (string) $category,
                'types' => collect($types)->map(fn (array $type, string $key) => [
                    'key' => $key,
                    'name' => (string) ($type['name'] ?? $key),
                    'description' => (string) ($type['description'] ?? ''),
                    'deprecated' => (bool) ($type['deprecated'] ?? false),
                    'experimental' => (bool) ($type['experimental'] ?? false),
                    'builds' => (int) ($type['builds'] ?? 0),
                    'minecraft_versions' => (int) ($type['versions']['minecraft'] ?? 0),
                    'project_versions' => (int) ($type['versions']['project'] ?? 0),
                ])->values()->all(),
            ];
        }

        return [
            'current' => $server->hydra_minecraft,
            'categories' => $categories,
        ];
    }

    /**
     * Versions of a type, newest first.
     */
    public function versions(Request $request, Server $server, string $type): array
    {
        $this->check($request, $server);

        $versions = collect($this->client->versions($type))
            ->map(fn (array $version, string $id) => [
                'id' => $id,
                'type' => (string) ($version['type'] ?? 'RELEASE'),
                'supported' => (bool) ($version['supported'] ?? true),
                'java' => isset($version['java']) ? (int) $version['java'] : null,
                'builds' => (int) ($version['builds'] ?? 0),
                'created' => $version['created'] ?? null,
            ])
            ->reverse()
            ->values();

        return ['data' => $versions->all()];
    }

    /**
     * Builds of a version, newest first.
     */
    public function builds(Request $request, Server $server, string $type, string $version): array
    {
        $this->check($request, $server);

        $builds = collect($this->client->builds($type, $version))->map(fn (array $build) => [
            'id' => (int) $build['id'],
            'name' => (string) ($build['name'] ?? $build['buildNumber'] ?? $build['id']),
            'experimental' => (bool) ($build['experimental'] ?? false),
            'size' => (int) ($build['jarSize'] ?? $build['zipSize'] ?? 0),
            'changes' => array_values(array_filter(array_map('strval', array_slice($build['changes'] ?? [], 0, 10)))),
            'created' => $build['created'] ?? null,
        ]);

        return ['data' => $builds->values()->all()];
    }

    /**
     * Installs a build, optionally on an emptied server and with the EULA accepted.
     *
     * @throws \Throwable
     */
    public function install(Request $request, Server $server): array
    {
        $this->check($request, $server);
        $data = $request->validate([
            'build' => 'required|integer|min:1',
            'wipe' => 'sometimes|boolean',
            'eula' => 'sometimes|boolean',
        ]);

        set_time_limit(900);
        $result = $this->service->install($server, (int) $data['build'], (bool) ($data['wipe'] ?? false), (bool) ($data['eula'] ?? false));

        Activity::event('server:minecraft.install')
            ->property('software', $this->client->type($result['type'])['name'] ?? $result['type'])
            ->property('version', $result['version'])
            ->property('build', $result['build'])
            ->property('wiped', (bool) ($data['wipe'] ?? false))
            ->log();

        return $result;
    }

    /**
     * Icon of a type, loaded by the panel so browsers do not contact mcjars.
     */
    public function icon(Request $request, Server $server, string $type): Response
    {
        $this->check($request, $server);

        $icon = preg_match(McJarsClient::TYPE_PATTERN, $type) ? $this->client->icon($type) : null;
        if (is_null($icon)) {
            throw new NotFoundHttpException();
        }

        return new Response($icon, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    private function check(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_SETTINGS_VERSION, $server)) {
            throw new AuthorizationException();
        }
        if (!$this->addon->offersFor($server)) {
            throw new NotFoundHttpException();
        }
    }
}
