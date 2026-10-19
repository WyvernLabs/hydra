<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Mockery\MockInterface;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\AdminRole;
use Illuminate\Support\Facades\File;
use Pterodactyl\Services\Hydra\UpdateService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Repositories\Wings\DaemonUpdateRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: updates of the panel and the nodes from the admin area.
 */
class UpdateControllerTest extends HttpTestCase
{
    private string $dir;

    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $this->dir = UpdateService::directory();
        File::deleteDirectory($this->dir);
        File::ensureDirectoryExists($this->dir);
    }

    public function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function installed(string $commit = 'aaaaaaa1111111111111111111111111111111111'): void
    {
        file_put_contents("$this->dir/installed.json", json_encode(['commit' => $commit, 'subject' => 'Old']));
    }

    public function testRequestsNeedTheUpdaterToBeSetUp(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings/updates')->assertOk()->assertSee('hydra-panel update');
        $this->actingAs($admin)->postJson('/admin/settings/updates/panel')->assertStatus(409);
        $this->assertFileDoesNotExist("$this->dir/request");
    }

    public function testCheckAndUpdateLeaveARequestForTheUpdater(): void
    {
        $admin = User::factory()->admin()->create();
        $this->installed();

        $this->actingAs($admin)->postJson('/admin/settings/updates/check')->assertStatus(202);
        $this->assertSame('check', file_get_contents("$this->dir/request"));

        // Only one request at a time.
        $this->actingAs($admin)->postJson('/admin/settings/updates/panel')->assertStatus(409);

        unlink("$this->dir/request");
        $this->actingAs($admin)->postJson('/admin/settings/updates/panel')->assertStatus(202);
        $this->assertSame('update', file_get_contents("$this->dir/request"));
    }

    public function testStatusShowsWhetherAnUpdateIsAvailable(): void
    {
        $admin = User::factory()->admin()->create();
        $this->installed();
        file_put_contents("$this->dir/latest.json", json_encode(['commit' => 'bbbbbbb2222222222222222222222222222222222', 'subject' => 'New']));
        file_put_contents("$this->dir/panel.log", "\e[32m==>\e[0m Fertig");

        $this->actingAs($admin)->getJson('/admin/settings/updates/status')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest.subject', 'New')
            ->assertJsonPath('log', '==> Fertig');
    }

    public function testNodesAreUpdatedThroughWings(): void
    {
        $admin = User::factory()->admin()->create();
        /** @var Node $node */
        $node = Node::factory()->create(['location_id' => Location::factory()->create()->id]);

        $this->mock(DaemonUpdateRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->status')->andReturn(['version' => '1.0.0-dev+abc1234', 'available' => true, 'running' => false]);
            $mock->expects('setNode->start');
        });

        $this->actingAs($admin)->getJson("/admin/settings/updates/nodes/$node->id")
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('version', '1.0.0-dev+abc1234');

        $this->actingAs($admin)->postJson("/admin/settings/updates/nodes/$node->id")->assertStatus(202);
    }

    public function testUnreachableNodeIsReported(): void
    {
        $admin = User::factory()->admin()->create();
        $node = Node::factory()->create(['location_id' => Location::factory()->create()->id]);

        $this->mock(DaemonUpdateRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->status')->andThrow(new DaemonConnectionException(new \GuzzleHttp\Exception\TransferException('down')));
        });

        $this->actingAs($admin)->getJson("/admin/settings/updates/nodes/$node->id")->assertOk()->assertJsonPath('online', false);
    }

    public function testRunningUpdatesNeedThePermission(): void
    {
        $this->installed();
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Watcher', 'color' => '#3b9eea', 'permissions' => ['updates.view']]));

        $this->actingAs($user)->get('/admin/settings/updates')->assertOk();
        $this->actingAs($user)->postJson('/admin/settings/updates/panel')->assertForbidden();
        $this->assertFileDoesNotExist("$this->dir/request");
    }
}
