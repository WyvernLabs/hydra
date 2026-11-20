<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Addons\PluginsAddon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Concerns\ProxiesProjectIcons;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\ModrinthSource;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginManagerService;

/**
 * Hydra: plugin manager (addon "plugins").
 */
class PluginController extends ClientApiController
{
    use ProxiesProjectIcons;

    private const PER_PAGE = 20;

    public function __construct(private PluginsAddon $addon, private PluginManagerService $manager)
    {
        parent::__construct();
    }

    /**
     * Whether the page is shown: addon on, egg chosen, plugins folder there.
     */
    public function available(Request $request, Server $server): array
    {
        $this->check($request, $server);

        return ['available' => !is_null($this->manager->folder($server))];
    }

    /**
     * Everything the page needs to start: sources, software, Minecraft
     * versions and what the server most likely runs.
     */
    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server);

        $sources = collect($this->manager->sources())->map(fn (PluginSource $source, string $key) => [
            'key' => $key,
            'name' => $source->name(),
            'software' => array_values(array_filter(array_keys(PluginSource::SOFTWARE), fn (string $software) => $source->supports($software))),
        ])->values()->all();

        try {
            $versions = app(ModrinthSource::class)->gameVersions();
        } catch (\Throwable) {
            $versions = [];
        }

        return [
            'folder' => $this->manager->folder($server, true),
            'sources' => $sources,
            'software' => collect(PluginSource::SOFTWARE)->map(fn (string $family, string $key) => ['key' => $key, 'family' => $family])->values()->all(),
            'minecraft_versions' => $versions,
            'defaults' => $this->manager->defaults($server),
        ];
    }

    public function search(Request $request, Server $server): array
    {
        $this->check($request, $server);
        $data = $this->filters($request, [
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|string|in:relevance,downloads,updated,newest',
            'page' => 'nullable|integer|min:1|max:500',
        ]);
        $source = $this->manager->source($data['source']);
        if (!$source->supports($data['software'])) {
            return ['data' => [], 'more' => false];
        }

        $page = (int) ($data['page'] ?? 1);
        $result = $source->search(trim((string) ($data['q'] ?? '')), $data['software'], $data['version'] ?? null, $data['sort'] ?? 'relevance', $page, self::PER_PAGE);

        return ['data' => $result['data'], 'more' => $result['total'] > $page * self::PER_PAGE];
    }

    public function versions(Request $request, Server $server, string $source, string $project): array
    {
        $this->check($request, $server);
        $data = $this->filters($request, [], false);

        $versions = $this->manager->source($source)->versions($project, $data['software'], $data['version'] ?? null);

        // The download links stay on the panel.
        return ['data' => array_map(fn (array $version) => collect($version)->except('url')->all(), $versions)];
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

        return ['data' => (object) $this->manager->updates($server, $data['software'], $data['version'] ?? null)];
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

        set_time_limit(400);
        $before = \Pterodactyl\Models\HydraServerPlugin::query()
            ->where('server_id', $server->id)->where('source', $data['source'])->where('project_id', $data['project'])->first();
        $plugin = $this->manager->install($server, $request->user(), $data['source'], $data['project'], $data['version_id'] ?? null, $data['software'], $data['version'] ?? null);

        Activity::event($before ? 'server:plugins.update' : 'server:plugins.install')
            ->property('name', $plugin->name)
            ->property('version', $plugin->version_name)
            ->property('source', $this->manager->source($plugin->source)->name())
            ->log();

        return ['data' => ['file' => $plugin->file, 'name' => $plugin->name, 'version_name' => $plugin->version_name]];
    }

    public function toggle(Request $request, Server $server): Response
    {
        $this->check($request, $server, true);
        $data = $request->validate(['file' => 'required|string|max:200', 'enabled' => 'required|boolean']);

        $this->manager->toggle($server, $data['file'], (bool) $data['enabled']);
        Activity::event($data['enabled'] ? 'server:plugins.enable' : 'server:plugins.disable')->property('file', basename($data['file']))->log();

        return $this->returnNoContent();
    }

    public function delete(Request $request, Server $server): Response
    {
        $this->check($request, $server, true);
        $data = $request->validate(['file' => 'required|string|max:200']);

        $record = $this->manager->delete($server, $data['file']);
        Activity::event('server:plugins.delete')->property('name', $record?->name ?? basename($data['file']))->log();

        return $this->returnNoContent();
    }

    /**
     * Icon of a plugin, loaded by the panel so browsers do not contact the
     * sources. Only https addresses of the icon hosts of the sources work.
     */
    public function icon(Request $request, Server $server): Response
    {
        $this->check($request, $server);
        $hosts = collect($this->manager->sources())->flatMap(fn (PluginSource $source) => $source->iconHosts())->all();

        return $this->proxyIcon((string) $request->query('u', ''), $hosts);
    }

    /**
     * Source, software and Minecraft version every request of the page carries.
     */
    private function filters(Request $request, array $rules, bool $source = true): array
    {
        $data = $request->validate(array_merge([
            'source' => $source ? 'required|string|in:modrinth,hangar,spigot' : 'nullable',
            'software' => 'required|string|in:' . implode(',', array_keys(PluginSource::SOFTWARE)),
            'version' => ['nullable', 'string', 'regex:/^\d+\.\d+(\.\d+)?$/'],
        ], $rules));

        // Proxies do not run a Minecraft version of their own.
        if (PluginSource::SOFTWARE[$data['software']] === 'proxy') {
            $data['version'] = null;
        }

        return $data;
    }

    private function check(Request $request, Server $server, bool $manage = false): void
    {
        $permission = $manage ? Permission::ACTION_PLUGINS_MANAGE : Permission::ACTION_PLUGINS_READ;
        if (!$request->user()->can($permission, $server)) {
            throw new AuthorizationException();
        }
        if (!$this->addon->offersFor($server)) {
            throw new NotFoundHttpException();
        }
    }
}
