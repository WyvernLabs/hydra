<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Services\Hydra\Support\SupportTabs;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: game support in the admin area (addon "gamesupport").
 */
class GameSupportTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.gamesupport.enabled', true);
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create();
        $role = AdminRole::query()->create(['name' => 'Support ' . uniqid(), 'color' => '#2fb67c', 'permissions' => $permissions]);
        $user->adminRoles()->attach($role);

        return $user->refresh();
    }

    private function permissionsOf(string $token): array
    {
        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);

        return $payload['permissions'] ?? [];
    }

    public function testServerIsFoundByShortIdFullUuidOrLink(): void
    {
        $server = $this->createServerModel();

        $this->assertTrue($server->is(SupportTabs::find($server->uuidShort)));
        $this->assertTrue($server->is(SupportTabs::find(strtoupper($server->uuid))));
        $this->assertTrue($server->is(SupportTabs::find("https://panel.test/server/$server->uuidShort/files")));
        // A part of the host with eight characters is not taken for the ID.
        $this->assertTrue($server->is(SupportTabs::find("https://panelabc.test/server/$server->uuidShort")));
        $this->assertNull(SupportTabs::find('nothing'));
        $this->assertNull(SupportTabs::find('0000000a'));
    }

    public function testTabIsOpenedClosedAndLogged(): void
    {
        $server = $this->createServerModel();
        $staff = $this->staff(['support.view']);

        $this->actingAs($staff)->post('/admin/support', ['server' => $server->uuidShort])
            ->assertRedirect("/admin/support?tab=$server->id");
        $this->assertTrue(app(SupportTabs::class)->isOpen($server));

        // The owner sees it in the activity log of the server.
        $log = ActivityLog::query()->where('event', 'server:support.open')->firstOrFail();
        $this->assertSame($staff->id, $log->actor_id);
        $this->assertTrue($log->subjects()->where('subject_id', $server->id)->exists());

        $this->actingAs($staff)->delete("/admin/support/$server->id")->assertNoContent();
        $this->assertFalse(app(SupportTabs::class)->isOpen($server));
        $this->actingAs($staff)->getJson("/admin/support/$server->id/websocket")->assertForbidden();
    }

    public function testUnknownServerShowsAnError(): void
    {
        $this->actingAs($this->staff(['support.view']))->post('/admin/support', ['server' => 'deadbeef'])
            ->assertRedirect('/admin/support');
        $this->assertEmpty(app(SupportTabs::class)->all());
    }

    public function testTokenDependsOnTheSupportPermissions(): void
    {
        $server = $this->createServerModel();

        $viewer = $this->staff(['support.view']);
        $this->actingAs($viewer)->post('/admin/support', ['server' => $server->uuid]);
        $data = $this->actingAs($viewer)->getJson("/admin/support/$server->id/websocket")->assertOk()->json();
        $this->assertStringEndsWith("/api/servers/$server->uuid/ws", $data['socket']);
        $permissions = $this->permissionsOf($data['token']);
        $this->assertContains('websocket.connect', $permissions);
        $this->assertNotContains('control.start', $permissions);
        $this->assertNotContains('control.console', $permissions);

        $controller = $this->staff(['support.control']);
        $this->actingAs($controller)->post('/admin/support', ['server' => $server->uuid]);
        $permissions = $this->permissionsOf($this->actingAs($controller)->getJson("/admin/support/$server->id/websocket")->assertOk()->json('token'));
        $this->assertContains('control.start', $permissions);
        $this->assertContains('control.console', $permissions);
    }

    public function testTabsCloseAfterFourHours(): void
    {
        $server = $this->createServerModel();
        $staff = $this->staff(['support.view']);
        $this->actingAs($staff)->post('/admin/support', ['server' => $server->uuidShort]);

        $this->travel(SupportTabs::LIFETIME_MINUTES - 1)->minutes();
        $this->actingAs($staff)->getJson("/admin/support/$server->id/websocket")->assertOk();

        $this->travel(2)->minutes();
        $this->actingAs($staff)->getJson("/admin/support/$server->id/websocket")->assertForbidden();
        $this->assertEmpty(app(SupportTabs::class)->all());
    }

    public function testSuspensionNeedsItsPermissionAndAnOpenTab(): void
    {
        $this->mock(\Pterodactyl\Repositories\Wings\DaemonServerRepository::class, function ($mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('sync');
        });
        $server = $this->createServerModel();

        $viewer = $this->staff(['support.view']);
        $this->actingAs($viewer)->post('/admin/support', ['server' => $server->uuidShort]);
        $this->actingAs($viewer)->postJson("/admin/support/$server->id/suspension", ['action' => 'suspend'])->assertForbidden();

        $staff = $this->staff(['support.view', 'support.suspend']);
        // Without an open tab nothing happens.
        app(SupportTabs::class)->close($server);
        $this->actingAs($staff)->postJson("/admin/support/$server->id/suspension", ['action' => 'suspend'])->assertForbidden();

        $this->actingAs($staff)->post('/admin/support', ['server' => $server->uuidShort]);
        $this->actingAs($staff)->postJson("/admin/support/$server->id/suspension", ['action' => 'suspend'])->assertOk();
        $this->assertTrue($server->refresh()->isSuspended());
        $this->assertTrue(ActivityLog::query()->where('event', 'server:support.suspend')->exists());

        $this->actingAs($staff)->postJson("/admin/support/$server->id/suspension", ['action' => 'unsuspend'])->assertOk();
        $this->assertFalse($server->refresh()->isSuspended());
    }

    public function testReinstallUsesTheReinstallService(): void
    {
        $server = $this->createServerModel();
        $this->mock(\Pterodactyl\Services\Servers\ReinstallServerService::class, function ($mock) {
            $mock->expects('handle')->once();
        });

        $this->actingAs($this->staff(['support.view', 'support.suspend']))->postJson("/admin/support/$server->id/reinstall")->assertForbidden();

        $staff = $this->staff(['support.view', 'support.reinstall']);
        $this->actingAs($staff)->post('/admin/support', ['server' => $server->uuidShort]);
        $this->actingAs($staff)->postJson("/admin/support/$server->id/reinstall")->assertOk();
        $this->assertTrue(ActivityLog::query()->where('event', 'server:support.reinstall')->exists());
    }

    public function testTransferOnlyToAnotherNodeWithFreeAllocations(): void
    {
        $server = $this->createServerModel();
        $other = \Pterodactyl\Models\Node::factory()->create(['location_id' => $server->node->location_id]);
        $free = \Pterodactyl\Models\Allocation::factory()->create(['node_id' => $other->id]);
        $taken = \Pterodactyl\Models\Allocation::factory()->create(['node_id' => $other->id, 'server_id' => $server->id]);

        $this->mock(\Pterodactyl\Services\Servers\TransferServerService::class, function ($mock) use ($server, $other, $free) {
            $mock->expects('handle')
                ->withArgs(fn ($s, $node, $allocation, $additional) => $s->id === $server->id && $node === $other->id && $allocation === $free->id && $additional === [])
                ->once()
                ->andReturn(new \Pterodactyl\Models\ServerTransfer());
        });

        $staff = $this->staff(['support.view', 'support.transfer']);
        $this->actingAs($staff)->post('/admin/support', ['server' => $server->uuidShort]);

        // Not to its own node, not to allocations that are taken.
        $this->actingAs($staff)->postJson("/admin/support/$server->id/transfer", ['node_id' => $server->node_id, 'allocation_id' => $server->allocation_id])->assertStatus(422);
        $this->actingAs($staff)->postJson("/admin/support/$server->id/transfer", ['node_id' => $other->id, 'allocation_id' => $taken->id])->assertStatus(400);

        $this->actingAs($staff)->postJson("/admin/support/$server->id/transfer", ['node_id' => $other->id, 'allocation_id' => $free->id])->assertOk();
    }

    public function testAccessNeedsPermissionAndAddon(): void
    {
        $server = $this->createServerModel();

        $this->actingAs($this->staff(['users.view']))->post('/admin/support', ['server' => $server->uuidShort])->assertForbidden();

        config()->set('hydra.addons.gamesupport.enabled', false);
        $this->actingAs($this->staff(['support.view']))->post('/admin/support', ['server' => $server->uuidShort])->assertNotFound();
    }
}
