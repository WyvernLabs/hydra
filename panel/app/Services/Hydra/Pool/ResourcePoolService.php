<?php

namespace Pterodactyl\Services\Hydra\Pool;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\HydraResourcePool;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Services\Hydra\Addons\ProxyAddon;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Hydra\Addons\ResourcePoolAddon;
use Pterodactyl\Services\Hydra\Addons\SubdomainsAddon;
use Pterodactyl\Services\Servers\BuildModificationService;

/**
 * Hydra: the resource pool of a user (addon "resource_pool"). Every server of
 * the user marked as a pool server takes its limits from the pool; the user
 * creates, resizes and deletes these servers within it.
 */
class ResourcePoolService
{
    // Smallest server: a tenth of a core, 256 MiB memory, 512 MiB disk, one port.
    public const MINIMUM = ['cpu' => 10, 'memory' => 256, 'disk' => 512, 'ports' => 1];

    public function __construct(
        private ResourcePoolAddon $addon,
        private ServerCreationService $creation,
        private BuildModificationService $build,
        private ServerDeletionService $deletion,
    ) {
    }

    /**
     * The pool of the user, null when the addon is off or the user has none.
     */
    public function poolOf(User $user): ?HydraResourcePool
    {
        return $this->addon->enabled() ? $user->resourcePool()->first() : null;
    }

    /**
     * Servers of the user that count against the pool.
     *
     * @return Collection<int, Server>
     */
    public function servers(User $user): Collection
    {
        return Server::query()
            ->where('owner_id', $user->id)
            ->where('hydra_pool', true)
            ->with('egg')
            ->withCount(['allocations', 'databases', 'subdomains', 'proxies'])
            ->orderBy('name')
            ->get();
    }

    /**
     * What a server takes from the pool.
     *
     * @return array<string, int>
     */
    public function usage(Server $server): array
    {
        $allocations = $server->allocations_count ?? $server->allocations()->count();

        return [
            'cpu' => (int) $server->cpu,
            'memory' => (int) $server->memory,
            'disk' => (int) $server->disk,
            'databases' => (int) ($server->database_limit ?? 0),
            'ports' => max(1, (int) $server->allocation_limit, $allocations),
            'backups' => (int) $server->backup_storage_limit,
            'subdomains' => app(SubdomainsAddon::class)->enabled() ? app(SubdomainsAddon::class)->limit($server) : (int) ($server->hydra_subdomain_limit ?? 0),
            'proxies' => app(ProxyAddon::class)->enabled() ? app(ProxyAddon::class)->limit($server) : (int) ($server->hydra_proxy_limit ?? 0),
        ];
    }

    /**
     * What all pool servers of the user take together, with "servers" = their count.
     *
     * @return array<string, int>
     */
    public function used(User $user, ?Collection $servers = null): array
    {
        $servers ??= $this->servers($user);
        $used = ['servers' => $servers->count()] + array_fill_keys(HydraResourcePool::RESOURCES, 0);
        foreach ($servers as $server) {
            foreach ($this->usage($server) as $key => $value) {
                $used[$key] += $value;
            }
        }

        return $used;
    }

    /**
     * Eggs the user may pick, grouped by nothing, with the variables they fill in.
     *
     * @return Collection<int, Egg>
     */
    public function eggs(): Collection
    {
        $ids = $this->addon->eggIds();

        return Egg::query()
            ->when(!empty($ids), fn ($query) => $query->whereIn('id', $ids))
            ->with(['nest', 'variables'])
            ->orderBy('nest_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Locations the user may pick.
     *
     * @return Collection<int, Location>
     */
    public function locations(): Collection
    {
        $ids = $this->addon->locationIds();

        return Location::query()
            ->when(!empty($ids), fn ($query) => $query->whereIn('id', $ids))
            ->whereHas('nodes', fn ($query) => $query->where('public', true))
            ->orderBy('short')
            ->get();
    }

    /**
     * Validation rules of the resources of a server.
     */
    public static function resourceRules(): array
    {
        return [
            'cpu' => 'required|integer|min:' . self::MINIMUM['cpu'] . '|max:100000',
            'memory' => 'required|integer|min:' . self::MINIMUM['memory'] . '|max:16777216',
            'disk' => 'required|integer|min:' . self::MINIMUM['disk'] . '|max:1073741824',
            'databases' => 'required|integer|min:0|max:10000',
            'ports' => 'required|integer|min:' . self::MINIMUM['ports'] . '|max:10000',
            'backups' => 'required|integer|min:0|max:1073741824',
            'subdomains' => 'required|integer|min:0|max:10000',
            'proxies' => 'required|integer|min:0|max:10000',
        ];
    }

    /**
     * Creates a server from the pool. $data holds name, egg, location (or
     * null), the resources and the variables the user filled in.
     *
     * @throws \Throwable
     */
    public function create(User $user, HydraResourcePool $pool, array $data): Server
    {
        return $this->locked($user, function () use ($user, $pool, $data) {
            $used = $this->used($user);
            if (!is_null($pool->servers) && $used['servers'] >= $pool->servers) {
                throw ValidationException::withMessages(['name' => trans('client/pool.errors.server_limit')]);
            }
            $this->assertFits($pool, $used, $data);

            /** @var Egg $egg */
            $egg = $this->eggs()->firstWhere('id', (int) $data['egg']);
            if (!$egg) {
                throw ValidationException::withMessages(['egg' => trans('client/pool.errors.egg')]);
            }

            $locations = $this->locations()->pluck('id')->all();
            if (!empty($data['location'])) {
                if (!in_array((int) $data['location'], $locations, true)) {
                    throw ValidationException::withMessages(['location' => trans('client/pool.errors.location')]);
                }
                $locations = [(int) $data['location']];
            }
            if (empty($locations)) {
                throw ValidationException::withMessages(['location' => trans('client/pool.errors.location')]);
            }

            $server = $this->creation->handle([
                'name' => $data['name'],
                'description' => '',
                'owner_id' => $user->id,
                'egg_id' => $egg->id,
                'nest_id' => $egg->nest_id,
                'startup' => $egg->startup,
                'image' => array_values($egg->docker_images ?? [])[0] ?? '',
                'environment' => $this->environment($egg, $data['environment'] ?? []),
                'memory' => (int) $data['memory'],
                'swap' => 0,
                'disk' => (int) $data['disk'],
                'io' => 500,
                'cpu' => (int) $data['cpu'],
                'threads' => null,
                'oom_disabled' => true,
                'database_limit' => (int) $data['databases'],
                'allocation_limit' => (int) $data['ports'],
                'backup_limit' => 0,
                'backup_storage_limit' => (int) $data['backups'],
                'start_on_completion' => false,
            ], (new DeploymentObject())->setLocations($locations));

            $server->forceFill([
                'hydra_pool' => true,
                'hydra_subdomain_limit' => (int) $data['subdomains'],
                'hydra_proxy_limit' => (int) $data['proxies'],
            ])->saveOrFail();

            Activity::event('user:pool.server-created')->subject($server)->property('name', $server->name)->log();

            return $server;
        });
    }

    /**
     * Gives a pool server other resources.
     *
     * @throws \Throwable
     */
    public function update(User $user, HydraResourcePool $pool, Server $server, array $data): Server
    {
        return $this->locked($user, function () use ($user, $pool, $server, $data) {
            $server = Server::query()->withCount(['allocations', 'databases', 'subdomains', 'proxies'])->findOrFail($server->id);

            // Nothing that is in use can be taken away.
            $inUse = [
                'ports' => $server->allocations_count,
                'databases' => $server->databases_count,
                'subdomains' => $server->subdomains_count,
                'proxies' => $server->proxies_count,
            ];
            foreach ($inUse as $key => $count) {
                if ((int) $data[$key] < $count) {
                    throw ValidationException::withMessages([$key => trans('client/pool.errors.in_use', ['resource' => trans("client/pool.resources.$key"), 'count' => $count])]);
                }
            }

            $used = $this->used($user);
            foreach ($this->usage($server) as $key => $value) {
                $used[$key] -= $value;
            }
            $this->assertFits($pool, $used, $data);

            $server = $this->build->handle($server, [
                'memory' => (int) $data['memory'],
                'disk' => (int) $data['disk'],
                'cpu' => (int) $data['cpu'],
                'swap' => $server->swap,
                'io' => $server->io,
                'threads' => $server->threads,
                'oom_disabled' => $server->oom_disabled,
                'database_limit' => (int) $data['databases'],
                'allocation_limit' => (int) $data['ports'],
                'backup_limit' => $server->backup_limit,
                'backup_storage_limit' => (int) $data['backups'],
                'hydra_subdomain_limit' => (int) $data['subdomains'],
                'hydra_proxy_limit' => (int) $data['proxies'],
            ]);

            Activity::event('user:pool.server-updated')->subject($server)->property('name', $server->name)->log();

            return $server;
        });
    }

    /**
     * Deletes a pool server and gives its resources back to the pool.
     *
     * @throws \Throwable
     */
    public function delete(User $user, Server $server): void
    {
        $this->locked($user, function () use ($server) {
            $name = $server->name;
            $this->deletion->handle($server);

            Activity::event('user:pool.server-deleted')->property('name', $name)->log();
        });
    }

    /**
     * Everything the user's pool holds for the dashboard.
     */
    public function overview(User $user, HydraResourcePool $pool): array
    {
        $servers = $this->servers($user);

        return [
            'limits' => $pool->limits(),
            'used' => $this->used($user, $servers),
            'minimum' => self::MINIMUM,
            'servers' => $servers->map(fn (Server $server) => [
                'uuid' => $server->uuid,
                'identifier' => $server->uuidShort,
                'name' => $server->name,
                'egg' => $server->egg?->name,
                'status' => $server->status,
                'resources' => $this->usage($server),
                'in_use' => [
                    'ports' => $server->allocations_count,
                    'databases' => $server->databases_count,
                    'subdomains' => $server->subdomains_count,
                    'proxies' => $server->proxies_count,
                ],
            ])->values()->all(),
            'eggs' => $this->eggs()->map(fn (Egg $egg) => [
                'id' => $egg->id,
                'name' => $egg->name,
                'nest' => $egg->nest?->name,
                'description' => (string) $egg->description,
                'variables' => $egg->variables
                    ->filter(fn ($variable) => $variable->user_viewable && $variable->user_editable)
                    ->map(fn ($variable) => [
                        'env' => $variable->env_variable,
                        'name' => $variable->name,
                        'description' => (string) $variable->description,
                        'default' => (string) $variable->default_value,
                        'required' => str_contains('|' . $variable->rules . '|', '|required|'),
                    ])->values()->all(),
            ])->values()->all(),
            'locations' => $this->locations()->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->long ?: $location->short,
            ])->values()->all(),
            'features' => [
                'subdomains' => app(SubdomainsAddon::class)->enabled(),
                'proxies' => app(ProxyAddon::class)->enabled(),
            ],
            'can_edit' => $this->addon->userMayEdit(),
            'can_delete' => $this->addon->userMayDelete(),
        ];
    }

    /**
     * Every variable of the egg: what the user may change from $values, the
     * default for the rest.
     */
    private function environment(Egg $egg, array $values): array
    {
        $environment = [];
        foreach ($egg->variables as $variable) {
            $editable = $variable->user_viewable && $variable->user_editable;
            $environment[$variable->env_variable] = $editable && array_key_exists($variable->env_variable, $values)
                ? (string) ($values[$variable->env_variable] ?? '')
                : (string) $variable->default_value;
        }

        return $environment;
    }

    /**
     * @param array<string, int> $used what the other pool servers take
     *
     * @throws ValidationException
     */
    private function assertFits(HydraResourcePool $pool, array $used, array $data): void
    {
        $errors = [];
        foreach (HydraResourcePool::RESOURCES as $key) {
            $free = max(0, $pool->{$key} - $used[$key]);
            if ((int) $data[$key] > $free) {
                $errors[$key] = trans('client/pool.errors.not_enough', ['resource' => trans("client/pool.resources.$key")]);
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Runs $callback while no other change of the user's pool runs, so two
     * requests at once cannot take the same resources.
     */
    private function locked(User $user, \Closure $callback): mixed
    {
        return Cache::lock("hydra:pool:{$user->id}", 120)->block(30, $callback);
    }
}
