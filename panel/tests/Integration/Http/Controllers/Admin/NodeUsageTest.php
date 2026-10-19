<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Mockery\MockInterface;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\AdminRole;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: live resource usage of the nodes.
 */
class NodeUsageTest extends HttpTestCase
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
        return Node::factory()->create(['location_id' => Location::factory()->create()->id, 'hydra_bandwidth' => 1000]);
    }

    public function testUsageIsFetchedFromTheNode(): void
    {
        $admin = User::factory()->admin()->create();
        $node = $this->node();

        $this->mock(DaemonConfigurationRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->getUsage')->andReturn(['cpu' => 12.5, 'cpu_threads' => 4, 'memory_used' => 1, 'memory_total' => 4]);
        });

        $this->actingAs($admin)->getJson("/admin/nodes/view/$node->id/usage")
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('cpu', 12.5)
            ->assertJsonPath('bandwidth_bps', 125000000);
    }

    public function testOfflineNode(): void
    {
        $admin = User::factory()->admin()->create();
        $node = $this->node();

        $this->mock(DaemonConfigurationRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->getUsage')->andThrow(new DaemonConnectionException(new TransferException('down')));
        });

        $this->actingAs($admin)->getJson("/admin/nodes/view/$node->id/usage")->assertOk()->assertJsonPath('online', false);
    }

    public function testNodeListDoesNotContainTheNodeSecret(): void
    {
        $node = $this->node();
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Viewer', 'color' => '#3b9eea', 'permissions' => ['nodes.view']]));

        // Filtered, as other tests may leave enough nodes to fill the first page.
        $this->actingAs($user)->get('/admin/nodes?filter[uuid]=' . $node->uuid)
            ->assertOk()
            ->assertSee($node->name)
            ->assertDontSee($node->getDecryptedKey());
    }
}
