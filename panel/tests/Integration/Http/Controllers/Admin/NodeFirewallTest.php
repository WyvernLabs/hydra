<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Mockery\MockInterface;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\DatabaseHost;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Services\Hydra\NodeDatabaseService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Repositories\Wings\DaemonFirewallRepository;
use Pterodactyl\Services\Databases\Hosts\HostCreationService;
use Pterodactyl\Exceptions\Service\Hydra\NodeFirewallException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: firewall and database setup of the nodes.
 */
class NodeFirewallTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    private function node(): Node
    {
        return Node::factory()->create(['location_id' => Location::factory()->create()->id, 'fqdn' => '203.0.113.10']);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => $permissions]));

        return $user;
    }

    public function testFirewallNeedsItsPermission(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view']);

        $this->actingAs($user)->get("/admin/nodes/view/$node->id/firewall")->assertForbidden();
        $this->actingAs($user)->getJson("/admin/nodes/view/$node->id/firewall/state")->assertForbidden();
        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/firewall/rules", [])->assertForbidden();
        $this->actingAs($user)->get("/admin/nodes/view/$node->id/database")->assertForbidden();

        // The tabs are only shown with the permission.
        $this->actingAs($user)->get("/admin/nodes/view/$node->id/servers")
            ->assertOk()
            ->assertDontSee("/admin/nodes/view/$node->id/firewall");
    }

    public function testStateOfTheFirewall(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.firewall']);

        $this->mock(DaemonFirewallRepository::class, function (MockInterface $mock) {
            $mock->allows('setNode')->andReturnSelf();
            $mock->expects('state')->andReturn([
                'firewall' => ['installed' => true, 'active' => true, 'rules' => [['rule' => 'allow 22/tcp', 'action' => 'allow', 'comment' => 'SSH', 'protected' => true, 'managed' => false]]],
                'client_ip' => '198.51.100.5',
            ]);
        });

        $this->actingAs($user)->get("/admin/nodes/view/$node->id/firewall")->assertOk()->assertSee(__('admin/nodes.firewall.add'));
        $this->actingAs($user)->getJson("/admin/nodes/view/$node->id/firewall/state")
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('firewall.rules.0.rule', 'allow 22/tcp');
    }

    public function testOfflineNode(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.firewall']);

        $this->mock(DaemonFirewallRepository::class, function (MockInterface $mock) {
            $mock->allows('setNode')->andReturnSelf();
            $mock->expects('state')->andThrow(new DaemonConnectionException(new TransferException('down')));
        });

        $this->actingAs($user)->getJson("/admin/nodes/view/$node->id/firewall/state")
            ->assertStatus(502)
            ->assertJsonPath('online', false);
    }

    public function testRuleIsValidatedAndSent(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.firewall']);

        $this->mock(DaemonFirewallRepository::class, function (MockInterface $mock) {
            $mock->allows('setNode')->andReturnSelf();
            $mock->expects('addRule')->with([
                'action' => 'allow',
                'direction' => 'in',
                'protocol' => 'udp',
                'port' => '27015:27020',
                'address' => '10.0.0.0/8',
                'comment' => 'Steam',
            ]);
        });

        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/firewall/rules", [
            'action' => 'allow', 'direction' => 'in', 'protocol' => 'udp', 'port' => '27015:27020', 'address' => '10.0.0.0/8', 'comment' => 'Steam',
        ])->assertNoContent();

        foreach ([
            ['port' => '22; reboot'],
            ['address' => 'example.com'],
            ['action' => 'accept'],
            ['comment' => "it's"],
        ] as $invalid) {
            $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/firewall/rules", array_merge([
                'action' => 'allow', 'direction' => 'in', 'protocol' => 'tcp', 'port' => '22',
            ], $invalid))->assertUnprocessable();
        }
    }

    public function testErrorsOfTheNodeAreTranslated(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.firewall']);

        $this->mock(DaemonFirewallRepository::class, function (MockInterface $mock) {
            $mock->allows('setNode')->andReturnSelf();
            $mock->expects('deleteRule')->with('allow 22/tcp')->andThrow(new NodeFirewallException('rule_not_found'));
        });

        $this->actingAs($user)->deleteJson("/admin/nodes/view/$node->id/firewall/rules", ['rule' => 'allow 22/tcp'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'rule_not_found')
            ->assertJsonPath('error', __('admin/nodes.errors.rule_not_found'));
    }

    public function testDatabaseSetupStartsOnTheNode(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.database']);

        $this->mock(DaemonFirewallRepository::class, function (MockInterface $mock) {
            $mock->allows('setNode')->andReturnSelf();
            $mock->expects('setupDatabase')->withArgs(function (array $data) {
                return $data['username'] === 'hydra'
                    && strlen($data['password']) === 40
                    && $data['port'] === 3307
                    && in_array('198.51.100.5', $data['sources'], true)
                    && in_array('203.0.113.10', $data['sources'], true)
                    && $data['enable_firewall'] === true;
            });
        });

        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/database/setup", [
            'name' => 'Node DB',
            'host' => '203.0.113.10',
            'port' => 3307,
            'sources' => "198.51.100.5\n",
            'enable_firewall' => true,
        ])->assertNoContent();

        $this->assertSame(['198.51.100.5'], app(NodeDatabaseService::class)->extraSources());

        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/database/setup", [
            'name' => 'Node DB', 'host' => '203.0.113.10', 'port' => 3306, 'sources' => 'not-an-ip',
        ])->assertUnprocessable();

        Cache::forget('hydra:database-setup:' . $node->id);
    }

    public function testFinishedSetupAddsTheDatabaseHost(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.database']);
        $id = null;

        $this->mock(DaemonFirewallRepository::class, function (MockInterface $mock) use (&$id) {
            $mock->allows('setNode')->andReturnSelf();
            $mock->expects('setupDatabase')->withArgs(function (array $data) use (&$id) {
                $id = $data['id'];

                return true;
            });
            $mock->expects('database')->twice()->andReturnUsing(function () use (&$id) {
                return ['installed' => true, 'running' => true, 'setup' => ['id' => $id, 'running' => false, 'success' => true, 'step' => 'done']];
            });
        });

        // The connection test of the panel is not part of this test.
        $this->mock(HostCreationService::class, function (MockInterface $mock) use ($node) {
            $mock->expects('handle')->withArgs(fn (array $data) => $data['node_id'] === $node->id && $data['username'] === 'hydra' && $data['port'] === 3306)
                ->andReturnUsing(fn (array $data) => DatabaseHost::query()->create(array_merge($data, ['password' => encrypt($data['password'])])));
        });

        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/database/setup", [
            'name' => 'Node DB', 'host' => '203.0.113.10', 'port' => 3306, 'sources' => '198.51.100.5',
        ])->assertNoContent();

        $this->actingAs($user)->getJson("/admin/nodes/view/$node->id/database/status")
            ->assertOk()
            ->assertJsonPath('pending', false)
            ->assertJsonPath('finish_error', null)
            ->assertJsonPath('host.name', 'Node DB');

        $host = DatabaseHost::query()->where('node_id', $node->id)->firstOrFail();
        $this->assertTrue($host->hydra_managed);

        // Polled again, the host is not added a second time.
        $this->actingAs($user)->getJson("/admin/nodes/view/$node->id/database/status")->assertOk();
        $this->assertSame(1, DatabaseHost::query()->where('node_id', $node->id)->count());

        $host->delete();
    }
}
