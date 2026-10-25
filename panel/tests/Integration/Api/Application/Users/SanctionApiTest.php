<?php

namespace Pterodactyl\Tests\Integration\Api\Application\Users;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\BanTemplate;
use Pterodactyl\Models\UserSanction;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Tests\Integration\Api\Application\ApplicationApiIntegrationTestCase;

/**
 * Hydra: warnings and bans over the application API, used by a moderation
 * bot with its own admin role. A failed request rolls back the transaction of
 * the test (see Exceptions\Handler), so every forbidden request comes last.
 */
class SanctionApiTest extends ApplicationApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $daemon = \Mockery::mock(DaemonServerRepository::class)->shouldIgnoreMissing();
        $daemon->allows('setServer')->andReturnSelf();
        $this->app->instance(DaemonServerRepository::class, $daemon);
    }

    private function moderator(array $permissions = ['users.view', 'users.warn', 'users.ban', 'servers.view', 'servers.manage']): User
    {
        $moderator = User::factory()->create(['root_admin' => false]);
        $moderator->adminRoles()->attach(AdminRole::query()->create(['name' => 'Bot ' . uniqid(), 'color' => '#3b9eea', 'permissions' => $permissions]));
        $this->createNewDefaultApiKey($moderator, [
            'r_users' => AdminAcl::READ | AdminAcl::WRITE,
            'r_servers' => AdminAcl::READ | AdminAcl::WRITE,
        ]);

        return $moderator;
    }

    public function testWarningsAreCreatedAndListed(): void
    {
        $this->moderator();
        $user = User::factory()->create();

        $this->postJson("/api/application/users/$user->id/warnings", ['reason' => 'Spam in the chat'])
            ->assertCreated()
            ->assertJsonPath('object', UserSanction::RESOURCE_NAME)
            ->assertJsonPath('attributes.type', 'warning')
            ->assertJsonPath('attributes.reason', 'Spam in the chat');

        $this->getJson("/api/application/users/$user->id/sanctions")
            ->assertOk()
            ->assertJsonPath('data.0.attributes.type', 'warning');
    }

    public function testBanSuspendsServersAndLiftUnsuspendsThem(): void
    {
        $this->moderator();
        $server = $this->createServerModel();
        $user = $server->user;

        $id = $this->postJson("/api/application/users/$user->id/bans", ['reason' => 'Cheating', 'duration_days' => 7])
            ->assertCreated()
            ->assertJsonPath('attributes.type', 'ban')
            ->assertJsonPath('attributes.active', true)
            ->assertJsonPath('attributes.permanent', false)
            ->json('attributes.id');
        $this->assertSame(Server::STATUS_SUSPENDED, $server->refresh()->status);

        $this->getJson("/api/application/users/$user->id?include=sanctions")
            ->assertOk()
            ->assertJsonPath('attributes.relationships.sanctions.data.0.attributes.id', $id);

        $this->postJson("/api/application/users/$user->id/bans/$id/lift")->assertNoContent();
        $this->assertNull($server->refresh()->status);
        $this->assertNull($user->refresh()->activeBan());
    }

    public function testBanWithTemplate(): void
    {
        $this->moderator();
        $user = User::factory()->create();
        $template = BanTemplate::query()->create(['name' => 'Spam ' . uniqid(), 'reason' => 'Spam from the template', 'duration_days' => 3, 'sort' => 99]);

        $this->getJson('/api/application/ban-templates')
            ->assertOk()
            ->assertJsonFragment(['id' => $template->id, 'duration_days' => 3]);

        $this->postJson("/api/application/users/$user->id/bans", ['template' => $template->id])
            ->assertCreated()
            ->assertJsonPath('attributes.reason', 'Spam from the template');
        $this->assertTrue($user->activeBan()->expires_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));
    }

    public function testRootAdminsCannotBeBanned(): void
    {
        $this->moderator();
        $admin = User::factory()->create(['root_admin' => true]);

        $this->postJson("/api/application/users/$admin->id/bans", ['reason' => 'No'])->assertForbidden();
    }

    public function testBanNeedsTheBanPermissionOfTheKeyOwner(): void
    {
        $this->moderator(['users.view', 'users.warn']);
        $user = User::factory()->create();

        $this->postJson("/api/application/users/$user->id/bans", ['reason' => 'No'])->assertForbidden();
    }
}
