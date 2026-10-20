<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\Permission;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: on a suspended server everyone keeps files, databases and backups,
 * admins with "servers.suspended" keep everything, including starting it.
 */
class SuspendedServerAccessTest extends ClientApiIntegrationTestCase
{
    public function testOwnerKeepsOnlyFilesDatabasesAndBackups(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server->update(['status' => Server::STATUS_SUSPENDED]);

        $this->actingAs($user)->getJson($this->link($server))
            ->assertOk()
            ->assertJsonPath('attributes.status', 'suspended')
            ->assertJsonPath('attributes.can_use_suspended', false);

        $this->actingAs($user)->getJson($this->link($server, '/databases'))->assertOk();
        $this->actingAs($user)->getJson($this->link($server, '/backups'))->assertOk();

        $this->actingAs($user)->getJson($this->link($server, '/websocket'))->assertStatus(409);
        $this->actingAs($user)->getJson($this->link($server, '/startup'))->assertStatus(409);
        $this->actingAs($user)->getJson($this->link($server, '/activity'))->assertStatus(409);
        $this->actingAs($user)->postJson($this->link($server, '/power'), ['signal' => 'start'])->assertStatus(409);
        $this->actingAs($user)->postJson($this->link($server, '/settings/rename'), ['name' => 'x'])->assertStatus(409);
    }

    public function testSubuserPermissionsStillApplyWhileSuspended(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT, Permission::ACTION_FILE_READ]);
        $server->update(['status' => Server::STATUS_SUSPENDED]);

        $this->actingAs($user)->getJson($this->link($server, '/databases'))->assertForbidden();
    }

    public function testAdminWithPermissionKeepsFullAccessAndMayStart(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $role = AdminRole::query()->create([
            'name' => 'Support ' . Str::random(6),
            'color' => '#3b9eea',
            'permissions' => ['servers.view', 'servers.suspended'],
        ]);
        $user->adminRoles()->attach($role);
        $server->update(['status' => Server::STATUS_SUSPENDED]);

        $this->actingAs($user)->getJson($this->link($server))->assertJsonPath('attributes.can_use_suspended', true);
        $this->actingAs($user)->getJson($this->link($server, '/startup'))->assertOk();

        $response = $this->actingAs($user)->getJson($this->link($server, '/websocket'))->assertOk();
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($server->node->getDecryptedKey()));
        $permissions = $config->parser()->parse($response->json('data.token'))->claims()->get('permissions');
        $this->assertContains('admin.websocket.suspended', $permissions);

        $repository = $this->mock(DaemonPowerRepository::class);
        $repository->expects('setServer')->andReturnSelf();
        $repository->expects('send')->with('start', true)->andReturn(new \GuzzleHttp\Psr7\Response());

        $this->actingAs($user)->postJson($this->link($server, '/power'), ['signal' => 'start'])->assertNoContent();
    }

    public function testOwnerTokenDoesNotAllowSuspendedConsole(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $response = $this->actingAs($user)->getJson($this->link($server, '/websocket'))->assertOk();
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($server->node->getDecryptedKey()));

        $this->assertSame(['*'], $config->parser()->parse($response->json('data.token'))->claims()->get('permissions'));
    }
}
