<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\Permission;
use Pterodactyl\Repositories\Wings\DaemonRevocationRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: during a lockdown only the owner and admins with "servers.lockdown"
 * may use a server, everyone else is thrown out on the node right away.
 */
class LockdownTest extends ClientApiIntegrationTestCase
{
    /**
     * UUIDs of the users Wings was told to throw out.
     */
    private array $deauthorized = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.lockdown.enabled', true);

        $repository = $this->mock(DaemonRevocationRepository::class);
        $repository->allows('setNode')->andReturnSelf();
        $repository->allows('deauthorize')->andReturnUsing(function (string $user) {
            $this->deauthorized[] = $user;
        });
    }

    public function testOwnerLocksOutSubusers(): void
    {
        [$subuser, $server] = $this->generateTestAccount([
            Permission::ACTION_WEBSOCKET_CONNECT,
            Permission::ACTION_FILE_READ,
            Permission::ACTION_FILE_SFTP,
        ]);
        $owner = $server->user;

        $this->login($owner)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => true])->assertNoContent();
        $this->assertNotNull($server->refresh()->hydra_lockdown_at);
        $this->assertContains($subuser->uuid, $this->deauthorized);
        $this->assertNotContains($owner->uuid, $this->deauthorized);

        // The owner keeps everything.
        $this->login($owner)->getJson($this->link($server, '/websocket'))->assertOk();
        $this->login($owner)->getJson($this->link($server))
            ->assertJsonPath('attributes.lockdown.active', true)
            ->assertJsonPath('attributes.lockdown.can_toggle', true)
            ->assertJsonPath('attributes.lockdown.locked_out', false);

        $this->login($owner)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => false])->assertNoContent();
        $this->assertNull($server->refresh()->hydra_lockdown_at);
    }

    public function testLockedOutSubuserOnlyLearnsWhy(): void
    {
        [$subuser, $server] = $this->generateTestAccount([
            Permission::ACTION_WEBSOCKET_CONNECT,
            Permission::ACTION_FILE_READ,
        ]);
        $server->forceFill(['hydra_lockdown_at' => now()])->save();

        $this->login($subuser)->getJson($this->link($server))
            ->assertOk()
            ->assertJsonPath('meta.is_server_owner', false)
            ->assertJsonPath('meta.user_permissions', [])
            ->assertJsonPath('attributes.lockdown.locked_out', true)
            ->assertJsonPath('attributes.lockdown.can_toggle', false);
        $this->login($subuser)->getJson($this->link($server, '/websocket'))
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'ServerLockedDownException');
        $this->login($subuser)->getJson($this->link($server, '/files/list?directory=/'))->assertForbidden();
        $this->login($subuser)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => false])->assertForbidden();
        $this->assertNotNull($server->refresh()->hydra_lockdown_at);

        // Once lifted the subuser is back.
        $server->forceFill(['hydra_lockdown_at' => null])->save();
        $this->login($subuser)->getJson($this->link($server, '/websocket'))->assertOk();
    }

    public function testSftpRefusesLockedOutUsers(): void
    {
        [$subuser, $server] = $this->generateTestAccount([Permission::ACTION_FILE_SFTP, Permission::ACTION_FILE_READ]);
        $subuser->update(['password' => password_hash('foobar', PASSWORD_DEFAULT)]);
        $node = $server->node;
        $this->withHeader('Authorization', 'Bearer ' . $node->daemon_token_id . '.' . decrypt($node->daemon_token));
        $data = ['username' => $subuser->username . '.' . $server->uuidShort, 'password' => 'foobar'];

        $this->postJson('/api/remote/sftp/auth', $data)->assertOk();

        $server->forceFill(['hydra_lockdown_at' => now()])->save();
        $this->postJson('/api/remote/sftp/auth', $data)->assertForbidden();
    }

    public function testSubuserCannotStartLockdown(): void
    {
        [$subuser, $server] = $this->generateTestAccount([Permission::ACTION_SETTINGS_RENAME, Permission::ACTION_SETTINGS_REINSTALL]);

        $this->login($subuser)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => true])->assertForbidden();
        $this->assertNull($server->refresh()->hydra_lockdown_at);
    }

    public function testAdminsNeedTheLockdownPermission(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        $server->forceFill(['hydra_lockdown_at' => now()])->save();

        $support = $this->admin(['servers.view', 'servers.access']);
        $lockdown = $this->admin(['servers.view', 'servers.access', 'servers.lockdown']);

        $this->login($support)->getJson($this->link($server, '/websocket'))->assertForbidden();
        $this->login($lockdown)->getJson($this->link($server, '/websocket'))->assertOk();

        $this->login($lockdown)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => false])->assertNoContent();
        $this->assertNull($server->refresh()->hydra_lockdown_at);

        // Starting it throws out admins without the permission, but not those with it.
        $this->login($lockdown)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => true])->assertNoContent();
        $this->assertContains($support->uuid, $this->deauthorized);
        $this->assertNotContains($lockdown->uuid, $this->deauthorized);
    }

    public function testSuspensionComesFirstAndLockdownStays(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        $server->forceFill(['hydra_lockdown_at' => now(), 'status' => Server::STATUS_SUSPENDED])->save();

        // The owner cannot lift the lockdown of a suspended server.
        $this->login($owner)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => false])->assertStatus(409);
        $this->assertTrue($server->refresh()->isLockedDown());

        // Once unsuspended the lockdown is still there until the owner lifts it.
        $server->forceFill(['status' => null])->save();
        $this->login($owner)->getJson($this->link($server))->assertJsonPath('attributes.lockdown.active', true);
        $this->login($owner)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => false])->assertNoContent();
        $this->assertFalse($server->refresh()->isLockedDown());
    }

    public function testNothingHappensWhileTheAddonIsOff(): void
    {
        [$subuser, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);
        $server->forceFill(['hydra_lockdown_at' => now()])->save();
        config()->set('hydra.addons.lockdown.enabled', false);

        // A stored lockdown has no effect and nobody can change it.
        $this->login($subuser)->getJson($this->link($server, '/websocket'))->assertOk();
        $this->login($server->user)->getJson($this->link($server))
            ->assertJsonPath('attributes.lockdown.active', false)
            ->assertJsonPath('attributes.lockdown.can_toggle', false);
        $this->login($server->user)->postJson($this->link($server, '/settings/lockdown'), ['lockdown' => false])->assertForbidden();
        $this->assertNotNull($server->refresh()->hydra_lockdown_at);
    }

    private function admin(array $permissions): User
    {
        $user = User::factory()->create();
        $role = AdminRole::query()->create([
            'name' => 'Lockdown ' . Str::random(8),
            'color' => '#3b9eea',
            'permissions' => $permissions,
        ]);
        $user->adminRoles()->attach($role);

        return $user;
    }

    /**
     * Logs in as the user for the next request. The guards keep the user of
     * earlier requests and the API switches the default guard, so both are
     * reset when a test changes users.
     */
    private function login(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->actingAs($user);
    }
}
