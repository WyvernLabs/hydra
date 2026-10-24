<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Mockery\MockInterface;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\HydraResourcePool;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: resource pool (addon "resource_pool"): users create, resize and
 * delete their own servers within it; admins set it on the user's page.
 */
class ResourcePoolTest extends ClientApiIntegrationTestCase
{
    private Location $location;

    private Egg $egg;

    private array $daemon = [];

    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $this->location = Location::factory()->create();
        $node = Node::factory()->create(['location_id' => $this->location->id, 'memory' => 65536, 'disk' => 1048576]);
        foreach (range(30000, 30005) as $port) {
            Allocation::factory()->create(['node_id' => $node->id, 'port' => $port]);
        }
        $this->egg = Egg::query()->where('author', 'support@pterodactyl.io')->where('name', 'Bungeecord')->firstOrFail();

        config()->set('hydra.addons.resource_pool', [
            'enabled' => true,
            'eggs' => [$this->egg->id],
            'locations' => [$this->location->id],
            'user_edit' => true,
            'user_delete' => true,
        ]);

        $this->daemon = [];
        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('create')->andReturnUsing(fn () => $this->daemon[] = 'create');
            $mock->allows('sync')->andReturnUsing(fn () => $this->daemon[] = 'sync');
            $mock->allows('delete')->andReturnUsing(fn () => $this->daemon[] = 'delete');
        });
    }

    private function poolFor(User $user, array $limits = []): HydraResourcePool
    {
        return HydraResourcePool::query()->create(array_merge([
            'user_id' => $user->id,
            'servers' => 2,
            'cpu' => 300,
            'memory' => 4096,
            'disk' => 20480,
            'databases' => 2,
            'ports' => 4,
            'backups' => 10240,
            'subdomains' => 0,
            'proxies' => 0,
        ], $limits));
    }

    private function newServer(array $values = []): array
    {
        return array_merge([
            'name' => 'Pool server',
            'egg' => $this->egg->id,
            'location' => null,
            'cpu' => 100,
            'memory' => 1024,
            'disk' => 5120,
            'databases' => 1,
            'ports' => 2,
            'backups' => 2048,
            'subdomains' => 0,
            'proxies' => 0,
            'environment' => [],
        ], $values);
    }

    public function testPoolIsEmptyWithoutOneAndGoneWhileTheAddonIsOff(): void
    {
        $user = User::factory()->create();

        $this->login($user)->getJson('/api/client/account/pool')->assertOk()->assertJsonPath('pool', null);

        config()->set('hydra.addons.resource_pool.enabled', false);
        $this->login($user)->getJson('/api/client/account/pool')->assertNotFound();
    }

    public function testServerIsCreatedFromThePool(): void
    {
        $user = User::factory()->create();
        $this->poolFor($user);

        $response = $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer())->assertCreated();

        /** @var Server $server */
        $server = Server::query()->where('uuid', $response->json('uuid'))->firstOrFail();
        $this->assertTrue($server->hydra_pool);
        $this->assertSame($user->id, $server->owner_id);
        $this->assertSame([100, 1024, 5120, 1, 2, 2048], [$server->cpu, $server->memory, $server->disk, $server->database_limit, $server->allocation_limit, $server->backup_storage_limit]);
        $this->assertSame($this->location->id, $server->node->location_id);
        $this->assertContains('create', $this->daemon);
        $this->assertSame('bungeecord.jar', $server->variables()->where('env_variable', 'SERVER_JARFILE')->first()->server_value);

        $this->login($user)->getJson('/api/client/account/pool')
            ->assertOk()
            ->assertJsonPath('pool.used.servers', 1)
            ->assertJsonPath('pool.used.memory', 1024)
            ->assertJsonPath('pool.used.ports', 2)
            ->assertJsonPath('pool.servers.0.name', 'Pool server');
    }

    public function testServerMustFitIntoThePool(): void
    {
        $user = User::factory()->create();
        $this->poolFor($user, ['servers' => 1]);

        $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer(['memory' => 8192]))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.meta.source_field', 'memory');

        $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer())->assertCreated();

        // The pool allows one server only.
        $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer(['memory' => 256, 'disk' => 512]))
            ->assertStatus(422);

        $this->assertSame(1, Server::query()->where('owner_id', $user->id)->count());
    }

    public function testServersAreUnlimitedWithoutALimit(): void
    {
        $user = User::factory()->create();
        $this->poolFor($user, ['servers' => null]);

        $this->login($user)->getJson('/api/client/account/pool')->assertOk()->assertJsonPath('pool.limits.servers', null);
        foreach ([1, 2, 3] as $number) {
            $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer(['name' => "Server $number", 'memory' => 512, 'disk' => 1024, 'ports' => 1, 'databases' => 0, 'backups' => 0]))
                ->assertCreated();
        }

        $this->assertSame(3, Server::query()->where('owner_id', $user->id)->count());
    }

    public function testOnlyOfferedEggsCanBePicked(): void
    {
        $user = User::factory()->create();
        $this->poolFor($user);
        $other = Egg::query()->where('id', '!=', $this->egg->id)->firstOrFail();

        $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer(['egg' => $other->id]))
            ->assertStatus(422);
    }

    public function testServerIsResizedWithinThePool(): void
    {
        $user = User::factory()->create();
        $this->poolFor($user);
        $uuid = $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer())->json('uuid');
        $server = Server::query()->where('uuid', $uuid)->firstOrFail();

        $resources = array_intersect_key($this->newServer(), array_flip(HydraResourcePool::RESOURCES));

        // All of the memory of the pool, since the server gives its own back.
        $this->login($user)->patchJson("/api/client/account/pool/servers/$server->uuidShort", ['memory' => 4096] + $resources)
            ->assertOk()
            ->assertJsonPath('pool.used.memory', 4096);
        $this->assertSame(4096, $server->refresh()->memory);
        $this->assertContains('sync', $this->daemon);

        $this->login($user)->patchJson("/api/client/account/pool/servers/$server->uuidShort", ['memory' => 4352] + $resources)
            ->assertStatus(422);

        // Ports in use cannot be taken away (the server has its primary one).
        $this->login($user)->patchJson("/api/client/account/pool/servers/$server->uuidShort", ['ports' => 0] + $resources)
            ->assertStatus(422);
    }

    public function testPoolServerIsDeletedButNoOtherServer(): void
    {
        $user = User::factory()->create();
        $this->poolFor($user);
        $uuid = $this->login($user)->postJson('/api/client/account/pool/servers', $this->newServer())->json('uuid');
        $own = $this->createServerModel(['user_id' => $user->id]);

        // A server the admin gave the user outside the pool.
        $this->login($user)->deleteJson("/api/client/account/pool/servers/$own->uuid")->assertNotFound();

        config()->set('hydra.addons.resource_pool.user_delete', false);
        $this->login($user)->deleteJson("/api/client/account/pool/servers/$uuid")->assertForbidden();

        config()->set('hydra.addons.resource_pool.user_delete', true);
        $this->login($user)->deleteJson("/api/client/account/pool/servers/$uuid")->assertNoContent();
        $this->assertNull(Server::query()->where('uuid', $uuid)->first());
        $this->assertNotNull($own->fresh());
    }

    public function testAdminSetsThePoolInCoresAndGigabytes(): void
    {
        $user = User::factory()->create();
        $server = $this->createServerModel(['user_id' => $user->id]);
        $admin = User::factory()->admin()->create();

        $this->login($admin)->patch("/admin/users/view/$user->id/pool", [
            'active' => '1',
            'servers' => 3,
            'cpu' => 2.5,
            'memory' => 8,
            'disk' => 50,
            'databases' => 2,
            'ports' => 6,
            'backups' => 20,
            'subdomains' => 1,
            'proxies' => 1,
            'pool_servers' => [$server->id],
        ])->assertRedirect();

        $pool = $user->resourcePool()->firstOrFail();
        $this->assertSame([3, 250, 8192, 51200, 20480], [$pool->servers, $pool->cpu, $pool->memory, $pool->disk, $pool->backups]);
        $this->assertTrue($server->refresh()->hydra_pool);

        $this->login($admin)->get("/admin/users/view/$user->id")->assertOk()->assertSee('resourcePool');

        $this->login($admin)->patch("/admin/users/view/$user->id/pool", [
            'active' => '0', 'servers' => 0, 'cpu' => 0, 'memory' => 0, 'disk' => 0, 'databases' => 0,
            'ports' => 0, 'backups' => 0, 'subdomains' => 0, 'proxies' => 0,
        ])->assertRedirect();
        $this->assertNull($user->resourcePool()->first());
        $this->assertFalse($server->refresh()->hydra_pool);
    }

    /**
     * Requests as $user; switching users inside a test needs fresh guards.
     */
    private function login(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->actingAs($user);
    }
}
