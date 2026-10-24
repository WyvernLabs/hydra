<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\HydraProxy;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraDomain;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\HydraSubdomain;
use Illuminate\Http\Client\Request as HttpRequest;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Repositories\Wings\DaemonProxyRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: subdomains on Cloudflare, reverse proxies and free ports on a
 * dedicated node, and that all of it goes away with its port or server.
 */
class HydraNetworkTest extends ClientApiIntegrationTestCase
{
    private array $created = [];

    private array $deleted = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.subdomains.enabled', true);
        config()->set('hydra.addons.subdomains.default_limit', 2);
        config()->set('hydra.addons.proxy.enabled', true);
        config()->set('hydra.addons.proxy.default_limit', 1);

        $this->created = [];
        $this->deleted = [];
        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/dns_records')) {
                // "taken" exists already in Cloudflare.
                $taken = str_contains($request->url(), 'name=taken.');

                return Http::response(['success' => true, 'result' => $taken ? [['id' => 'manual']] : []]);
            }
            if ($request->method() === 'POST') {
                $this->created[] = $request->data();

                return Http::response(['success' => true, 'result' => ['id' => 'rec' . count($this->created)]]);
            }
            if ($request->method() === 'DELETE') {
                $this->deleted[] = basename(parse_url($request->url(), PHP_URL_PATH));

                return Http::response(['success' => true, 'result' => ['id' => 'x']]);
            }

            return Http::response(['success' => true, 'result' => [['id' => 'zone']]]);
        });
    }

    public function tearDown(): void
    {
        // Tests do not run in transactions (see the exception handler), so clean up.
        HydraProxy::query()->delete();
        HydraSubdomain::query()->delete();
        HydraDomain::query()->delete();

        parent::tearDown();
    }

    private function domain(): HydraDomain
    {
        return HydraDomain::query()->create([
            'name' => 'example.com',
            'zone_id' => 'zone1',
            'api_token' => 'token',
            'enabled' => true,
        ]);
    }

    public function testSubdomainWithSrvRecord(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $domain = $this->domain();
        $server->allocation->forceFill(['ip' => '203.0.113.10', 'port' => 25570])->save();
        $server->node->forceFill(['fqdn' => 'node.example.net'])->save();

        $this->actingAs($user)->postJson($this->link($server, '/network/subdomains'), [
            'domain' => $domain->id,
            'name' => 'Play',
            'allocation' => $server->allocation_id,
            'srv' => 'minecraft',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'play.example.com')
            ->assertJsonPath('srv', 'minecraft');

        $this->assertSame(['type' => 'A', 'name' => 'play.example.com', 'content' => '203.0.113.10', 'proxied' => false, 'ttl' => 1], $this->created[0]);
        $this->assertSame('SRV', $this->created[1]['type']);
        $this->assertSame('_minecraft._tcp.play.example.com', $this->created[1]['name']);
        $this->assertSame(25570, $this->created[1]['data']['port']);

        // Taken, blocked and invalid names are refused.
        foreach (['play', 'taken', 'www', '-bad-'] as $name) {
            $this->actingAs($user)->postJson($this->link($server, '/network/subdomains'), ['domain' => $domain->id, 'name' => $name])
                ->assertStatus(400);
        }

        // The limit of the server applies.
        $this->actingAs($user)->postJson($this->link($server, '/network/subdomains'), ['domain' => $domain->id, 'name' => 'second'])->assertOk();
        $this->actingAs($user)->postJson($this->link($server, '/network/subdomains'), ['domain' => $domain->id, 'name' => 'third'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'This server cannot have more subdomains.');

        // Without a port the name points to the node.
        $this->assertSame(['type' => 'CNAME', 'name' => 'second.example.com', 'content' => 'node.example.net'], array_intersect_key($this->created[2], array_flip(['type', 'name', 'content'])));

        $id = HydraSubdomain::query()->where('name', 'play')->value('id');
        $this->actingAs($user)->deleteJson($this->link($server, "/network/subdomains/$id"))->assertNoContent();
        $this->assertSame(['rec1', 'rec2'], $this->deleted);
    }

    public function testSubusersNeedAllocationPermissions(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_ALLOCATION_READ]);
        $domain = $this->domain();

        $this->actingAs($user)->getJson($this->link($server, '/network/hydra'))->assertOk()->assertJsonPath('subdomains.limit', 2);
        $this->actingAs($user)->postJson($this->link($server, '/network/subdomains'), ['domain' => $domain->id, 'name' => 'play'])->assertForbidden();
    }

    public function testProxyOnManagedSubdomain(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $domain = $this->domain();
        $server->allocation->forceFill(['ip' => '0.0.0.0', 'port' => 8100])->save();

        $this->mock(DaemonProxyRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->sync')->withArgs(fn (array $list) => $list === [['domain' => 'map.example.com', 'upstream' => '127.0.0.1:8100']]);
            $mock->expects('setNode->sync')->withArgs(fn (array $list) => $list === []);
        });

        $this->actingAs($user)->postJson($this->link($server, '/network/proxies'), [
            'allocation' => $server->allocation_id,
            'type' => 'subdomain',
            'domain_id' => $domain->id,
            'name' => 'map',
        ])->assertOk()->assertJsonPath('domain', 'map.example.com')->assertJsonPath('managed', true);

        // The subdomain of the proxy does not count as a subdomain.
        $this->actingAs($user)->getJson($this->link($server, '/network/hydra'))
            ->assertJsonCount(0, 'subdomains.items')
            ->assertJsonCount(1, 'proxies.items');

        // Limit reached.
        $this->actingAs($user)->postJson($this->link($server, '/network/proxies'), [
            'allocation' => $server->allocation_id, 'type' => 'own', 'domain' => 'other.example.org',
        ])->assertStatus(400);

        $id = HydraProxy::query()->value('id');
        $this->actingAs($user)->deleteJson($this->link($server, "/network/proxies/$id"))->assertNoContent();
        $this->assertDatabaseCount('hydra_subdomains', 0);
    }

    public function testOwnDomainHasToPointToTheNode(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/network/proxies'), [
            'allocation' => $server->allocation_id,
            'type' => 'own',
            'domain' => 'nothing-here.invalid',
        ])->assertStatus(400);
        $this->assertDatabaseCount('hydra_proxies', 0);
    }

    public function testDedicatedServerPicksItsPorts(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $other = $this->createServerModel(['node_id' => $server->node_id]);
        $server->forceFill(['hydra_dedicated' => true, 'allocation_limit' => 0])->save();
        $ip = $server->allocation->ip;
        Allocation::factory()->create(['node_id' => $server->node_id, 'ip' => $ip, 'port' => 30001, 'server_id' => $other->id]);

        $this->actingAs($user)->postJson($this->link($server, '/network/allocations-dedicated'), ['port' => 30000])
            ->assertOk()
            ->assertJsonPath('attributes.port', 30000);
        $this->actingAs($user)->postJson($this->link($server, '/network/allocations-dedicated'), ['port' => 30001])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'Port 30001 is already in use by another server.');
        $this->actingAs($user)->postJson($this->link($server, '/network/allocations-dedicated'), ['port' => (int) $server->node->daemonListen])
            ->assertStatus(400);
        $this->actingAs($user)->postJson($this->link($server, '/network/allocations-dedicated'), ['port' => 80])->assertStatus(400);

        // Removing the port deletes it, as the server added it itself.
        $allocation = Allocation::query()->where('port', 30000)->firstOrFail();
        $this->assertTrue($allocation->hydra_dedicated);
        $this->actingAs($user)->deleteJson($this->link($allocation))->assertNoContent();
        $this->assertDatabaseMissing('allocations', ['id' => $allocation->id]);

        // Servers without a dedicated node cannot.
        $server->forceFill(['hydra_dedicated' => false])->save();
        $this->actingAs($user)->postJson($this->link($server, '/network/allocations-dedicated'), ['port' => 30002])->assertStatus(400);
    }

    public function testDeletingTheServerRemovesEverything(): void
    {
        [, $server] = $this->generateTestAccount();
        $domain = $this->domain();
        $server->forceFill(['hydra_dedicated' => true])->save();
        $own = Allocation::factory()->create(['node_id' => $server->node_id, 'port' => 30500, 'server_id' => $server->id, 'hydra_dedicated' => true]);
        HydraSubdomain::query()->create(['server_id' => $server->id, 'domain_id' => $domain->id, 'name' => 'play', 'records' => ['a', 'b']]);
        HydraProxy::query()->create(['server_id' => $server->id, 'domain' => 'map.example.org', 'allocation_id' => $own->id]);

        $this->mock(DaemonProxyRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->sync')->withArgs(fn (array $list) => $list === []);
        });
        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->delete');
        });

        $this->app->make(ServerDeletionService::class)->handle($server);

        $this->assertSame(['a', 'b'], $this->deleted);
        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
        $this->assertDatabaseMissing('allocations', ['id' => $own->id]);
        $this->assertDatabaseCount('hydra_subdomains', 0);
        $this->assertDatabaseCount('hydra_proxies', 0);
    }

    public function testAdminAddsDomainWithCloudflareZone(): void
    {
        $admin = $this->createServerModel()->user;
        $admin->forceFill(['root_admin' => true])->save();

        $this->actingAs($admin)->post('/admin/design/domains', ['name' => 'Example.net', 'api_token' => 'secret'])
            ->assertRedirect();

        $domain = HydraDomain::query()->where('name', 'example.net')->firstOrFail();
        $this->assertSame('zone', $domain->zone_id);
        $this->assertSame('secret', $domain->api_token);
        $this->assertNotSame('secret', $domain->getRawOriginal('api_token'));
    }
}
