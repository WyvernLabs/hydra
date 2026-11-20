<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraServerMod;
use Pterodactyl\Services\Hydra\Addons\ModsAddon;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Minecraft\Mods\ModSource;
use Pterodactyl\Services\Hydra\Minecraft\Mods\ModManagerService;
use Pterodactyl\Services\Hydra\Minecraft\Mods\ModrinthModSource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Concerns\ProxiesProjectIcons;

/**
 * Hydra: mod manager (addon "mods").
 */
class ModController extends ClientApiController
{
    use ProxiesProjectIcons;

    private const PER_PAGE = 20;

    public function __construct(private ModsAddon $addon, private ModManagerService $manager)
    {
        parent::__construct();
    }

    /**
     * Whether the page is shown: addon on, egg chosen, mods folder there.
     */
    public function available(Request $request, Server $server): array
    {
        $this->check($request, $server);

        return ['available' => !is_null($this->manager->folder($server))];
    }

    /**
     * Everything the page needs to start: sources with their categories,
     * loaders, Minecraft versions and what the server most likely runs.
     */
    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server);

        $sources = collect($this->manager->sources())->map(function (ModSource $source, string $key) {
            try {
                $categories = $source->categories();
                $error = null;
            } catch (\Throwable $exception) {
                $categories = [];
                $error = $exception instanceof \Pterodactyl\Exceptions\DisplayException ? $exception->getMessage() : null;
            }

            return ['key' => $key, 'name' => $source->name(), 'categories' => $categories, 'error' => $error];
        })->values()->all();

        try {
            $versions = app(ModrinthModSource::class)->gameVersions();
        } catch (\Throwable) {
            $versions = [];
        }

        return [
            'folder' => $this->manager->folder($server, true),
            'sources' => $sources,
            'loaders' => ModSource::LOADERS,
            'minecraft_versions' => $versions,
            'defaults' => $this->manager->defaults($server),
        ];
    }

    public function search(Request $request, Server $server): array
    {
        $this->check($request, $server);
        $data = $this->filters($request, [
            'q' => 'nullable|string|max:100',
            'category' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'sort' => 'nullable|string|in:relevance,downloads,updated,newest',
            'page' => 'nullable|integer|min:1|max:500',
        ]);

        $page = (int) ($data['page'] ?? 1);
        $result = $this->manager->source($data['source'])->search(
            trim((string) ($data['q'] ?? '')),
            $data['loader'],
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
        $data = $this->filters($request, [], false);

        $versions = $this->manager->source($source)->versions($project, $data['loader'], $data['version'] ?? null);

        // The download links stay on the panel.
        return ['data' => array_map(fn (array $version) => collect($version)->except(['url', 'dependencies'])->all(), $versions)];
    }

    public function installed(Request $request, Server $server): array
    {
        $this->check($request, $server);

        return ['data' => $this->manager->installed($server)];
    }

    public function updates(Request $request, Server $server): array
    {
        $this->check($request, $server);
        $data = $this->filters($request, [], false);

        return ['data' => (object) $this->manager->updates($server, $data['loader'], $data['version'] ?? null)];
    }

    /**
     * @throws \Throwable
     */
    public function install(Request $request, Server $server): array
    {
        $this->check($request, $server, true);
        $data = $this->filters($request, [
            'project' => 'required|string|max:64',
            'version_id' => 'nullable|string|max:191',
        ]);

        set_time_limit(900);
        $before = HydraServerMod::query()
            ->where('server_id', $server->id)->where('source', $data['source'])->where('project_id', $data['project'])->exists();
        [$mod, $dependencies] = $this->manager->install($server, $request->user(), $data['source'], $data['project'], $data['version_id'] ?? null, $data['loader'], $data['version'] ?? null);

        $source = $this->manager->source($mod->source)->name();
        Activity::event($before ? 'server:mods.update' : 'server:mods.install')
            ->property('name', $mod->name)
            ->property('version', $mod->version_name)
            ->property('source', $source)
            ->log();
        foreach ($dependencies as $dependency) {
            Activity::event('server:mods.dependency')
                ->property('name', $dependency->name)
                ->property('version', $dependency->version_name)
                ->property('for', $mod->name)
                ->log();
        }

        return ['data' => [
            'file' => $mod->file,
            'name' => $mod->name,
            'version_name' => $mod->version_name,
            'dependencies' => array_map(fn (HydraServerMod $dependency) => $dependency->name, $dependencies),
        ]];
    }

    public function toggle(Request $request, Server $server): Response
    {
        $this->check($request, $server, true);
        $data = $request->validate(['file' => 'required|string|max:200', 'enabled' => 'required|boolean']);

        $this->manager->toggle($server, $data['file'], (bool) $data['enabled']);
        Activity::event($data['enabled'] ? 'server:mods.enable' : 'server:mods.disable')->property('file', basename($data['file']))->log();

        return $this->returnNoContent();
    }

    public function delete(Request $request, Server $server): Response
    {
        $this->check($request, $server, true);
        $data = $request->validate(['file' => 'required|string|max:200']);

        $record = $this->manager->delete($server, $data['file']);
        Activity::event('server:mods.delete')->property('name', $record?->name ?? basename($data['file']))->log();

        return $this->returnNoContent();
    }

    /**
     * Icon of a mod, loaded by the panel so browsers do not contact the
     * sources. Only https addresses of the icon hosts of the sources work.
     */
    public function icon(Request $request, Server $server): Response
    {
        $this->check($request, $server);
        $hosts = collect($this->manager->sources())->flatMap(fn (ModSource $source) => $source->iconHosts())->all();

        return $this->proxyIcon((string) $request->query('u', ''), $hosts);
    }

    /**
     * Source, loader and Minecraft version every request of the page carries.
     */
    private function filters(Request $request, array $rules, bool $source = true): array
    {
        return $request->validate(array_merge([
            'source' => $source ? 'required|string|in:modrinth,curseforge' : 'nullable',
            'loader' => 'required|string|in:' . implode(',', ModSource::LOADERS),
            'version' => ['nullable', 'string', 'regex:/^\d+\.\d+(\.\d+)?$/'],
        ], $rules));
    }

    private function check(Request $request, Server $server, bool $manage = false): void
    {
        $permission = $manage ? Permission::ACTION_MODS_MANAGE : Permission::ACTION_MODS_READ;
        if (!$request->user()->can($permission, $server)) {
            throw new AuthorizationException();
        }
        if (!$this->addon->offersFor($server)) {
            throw new NotFoundHttpException();
        }
    }
}
