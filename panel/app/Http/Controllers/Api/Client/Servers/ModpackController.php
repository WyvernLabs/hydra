<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Validation\Rule;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\ModpacksAddon;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Minecraft\Mods\ModSource;
use Pterodactyl\Services\Hydra\Minecraft\Mods\ModrinthModSource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Services\Hydra\Minecraft\Modpacks\ModpackSource;
use Pterodactyl\Services\Hydra\Minecraft\Modpacks\ModpackInstallService;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Concerns\ProxiesProjectIcons;

/**
 * Hydra: modpack installer (addon "modpacks").
 */
class ModpackController extends ClientApiController
{
    use ProxiesProjectIcons;

    private const PER_PAGE = 20;

    public function __construct(private ModpacksAddon $addon, private ModpackInstallService $installer)
    {
        parent::__construct();
    }

    /**
     * Sources with their categories, loaders, Minecraft versions, the pack
     * installed now and the last installation.
     */
    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server);

        $sources = collect($this->installer->sources())->map(function (ModpackSource $source, string $key) {
            try {
                [$categories, $error] = [$source->categories(), null];
            } catch (\Throwable $exception) {
                [$categories, $error] = [[], $exception instanceof DisplayException ? $exception->getMessage() : null];
            }

            return ['key' => $key, 'name' => $source->name(), 'categories' => $categories, 'error' => $error];
        })->values()->all();

        try {
            $versions = app(ModrinthModSource::class)->gameVersions();
        } catch (\Throwable) {
            $versions = [];
        }

        return [
            'sources' => $sources,
            'loaders' => ModSource::LOADERS,
            'minecraft_versions' => $versions,
            'profiles' => $this->installer->offersProfiles(),
            'current' => $server->hydra_minecraft['modpack'] ?? null,
            'install' => $this->installer->latest($server)?->toFrontend(),
        ];
    }

    public function search(Request $request, Server $server): array
    {
        $this->check($request, $server);
        $data = $request->validate([
            'source' => 'required|string|in:modrinth,curseforge',
            'loader' => 'nullable|string|in:' . implode(',', ModSource::LOADERS),
            'version' => ['nullable', 'string', 'regex:/^\d+\.\d+(\.\d+)?$/'],
            'category' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|string|in:relevance,downloads,updated,newest',
            'page' => 'nullable|integer|min:1|max:500',
        ]);

        $page = (int) ($data['page'] ?? 1);
        $result = $this->installer->source($data['source'])->search(
            trim((string) ($data['q'] ?? '')),
            (string) ($data['loader'] ?? ''),
            $data['version'] ?? null,
            $data['category'] ?? null,
            $data['sort'] ?? 'relevance',
            $page,
            self::PER_PAGE
        );

        return ['data' => $result['data'], 'more' => $result['total'] > $page * self::PER_PAGE];
    }

    public function versions(Request $request, Server $server, string $source, string $project): array
    {
        $this->check($request, $server);

        $versions = $this->installer->source($source)->versions($project, '', null);

        return ['data' => array_map(fn (array $version) => collect($version)->except('url')->all(), $versions)];
    }

    public function install(Request $request, Server $server): array
    {
        $this->check($request, $server, true);
        $data = $request->validate([
            'source' => 'required|string|in:modrinth,curseforge',
            'project' => 'required|string|max:64|regex:/^[A-Za-z0-9_.-]+$/',
            'version_id' => 'required|string|max:191',
            'mode' => ['required', Rule::in(['wipe', 'profile', 'keep'])],
            'eula' => 'required|boolean',
        ]);

        $install = $this->installer->start($server, $request->user(), $data['source'], $data['project'], $data['version_id'], $data['mode'], (bool) $data['eula']);

        Activity::event('server:modpacks.install')
            ->property('name', $install->name)
            ->property('version', $install->version_name)
            ->property('source', $this->installer->source($install->source)->name())
            ->property('mode', $install->mode)
            ->log();

        return ['data' => $install->refresh()->toFrontend()];
    }

    /**
     * Progress of the last installation.
     */
    public function status(Request $request, Server $server): array
    {
        $this->check($request, $server);

        return ['data' => $this->installer->latest($server)?->toFrontend()];
    }

    public function icon(Request $request, Server $server): Response
    {
        $this->check($request, $server);
        $hosts = collect($this->installer->sources())->flatMap(fn (ModpackSource $source) => $source->iconHosts())->all();

        return $this->proxyIcon((string) $request->query('u', ''), $hosts);
    }

    private function check(Request $request, Server $server, bool $install = false): void
    {
        $permission = $install ? Permission::ACTION_MODPACKS_INSTALL : Permission::ACTION_MODPACKS_READ;
        if (!$request->user()->can($permission, $server)) {
            throw new AuthorizationException();
        }
        if (!$this->addon->offersFor($server)) {
            throw new NotFoundHttpException();
        }
    }
}
