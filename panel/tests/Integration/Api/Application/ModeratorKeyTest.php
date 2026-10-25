<?php

namespace Pterodactyl\Tests\Integration\Api\Application;

use Pterodactyl\Models\User;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: a bot account with a narrow admin role (look at users and servers,
 * suspend servers) can use the application API for exactly that, even though
 * its key has write access to servers.
 */
class ModeratorKeyTest extends ApplicationApiIntegrationTestCase
{
    private User $moderator;

    public function setUp(): void
    {
        parent::setUp();

        $this->moderator = User::factory()->create(['root_admin' => false]);
        $this->moderator->adminRoles()->attach(AdminRole::query()->create([
            'name' => 'Moderator ' . uniqid(),
            'color' => '#3b9eea',
            'permissions' => ['servers.view', 'servers.manage', 'users.view'],
        ]));

        $this->createNewDefaultApiKey($this->moderator, [
            'r_servers' => AdminAcl::READ | AdminAcl::WRITE,
            'r_users' => AdminAcl::READ,
            'r_nodes' => AdminAcl::NONE,
            'r_allocations' => AdminAcl::NONE,
            'r_locations' => AdminAcl::NONE,
            'r_nests' => AdminAcl::NONE,
            'r_eggs' => AdminAcl::NONE,
            'r_database_hosts' => AdminAcl::NONE,
            'r_server_databases' => AdminAcl::NONE,
        ]);
    }

    public function testModeratorCanSuspendAndUnsuspendButNotChangeOrDeleteServers(): void
    {
        $server = $this->createServerModel();

        $daemon = \Mockery::mock(DaemonServerRepository::class)->shouldIgnoreMissing();
        $daemon->allows('setServer')->andReturnSelf();
        $this->app->instance(DaemonServerRepository::class, $daemon);

        $this->postJson("/api/application/servers/$server->id/suspend")->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertSame(Server::STATUS_SUSPENDED, $server->refresh()->status);

        $this->postJson("/api/application/servers/$server->id/unsuspend")->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertNull($server->refresh()->status);
    }

    // A failed request rolls back the transaction of the test (see Exceptions\Handler),
    // so every forbidden request is the last one of its test.

    public function testModeratorCannotDeleteServers(): void
    {
        $server = $this->createServerModel();

        $this->deleteJson("/api/application/servers/$server->id")->assertForbidden();
    }

    public function testModeratorCannotChangeServerDetails(): void
    {
        $server = $this->createServerModel();

        $this->patchJson("/api/application/servers/$server->id/details", ['name' => 'Renamed', 'user' => $server->owner_id])->assertForbidden();
    }

    public function testModeratorCanReadButNotChangeUsers(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/application/users/$user->id?include=servers")
            ->assertOk()
            ->assertJsonPath('attributes.email', $user->email);
        $this->getJson('/api/application/users?filter[email]=' . urlencode($user->email))
            ->assertOk()
            ->assertJsonPath('data.0.attributes.id', $user->id);

        $this->patchJson("/api/application/users/$user->id", ['username' => 'changed'])->assertForbidden();
    }

    public function testModeratorCannotSeeNodes(): void
    {
        $this->getJson('/api/application/nodes')->assertForbidden();
    }

    public function testKeysOfTheModeratorMayCarryWriteAccess(): void
    {
        $this->assertSame(AdminAcl::READ | AdminAcl::WRITE, AdminAcl::maxFor($this->moderator, AdminAcl::RESOURCE_SERVERS));
        $this->assertSame(AdminAcl::READ, AdminAcl::maxFor($this->moderator, AdminAcl::RESOURCE_USERS));
        $this->assertSame(AdminAcl::NONE, AdminAcl::maxFor($this->moderator, AdminAcl::RESOURCE_NODES));
    }
}
