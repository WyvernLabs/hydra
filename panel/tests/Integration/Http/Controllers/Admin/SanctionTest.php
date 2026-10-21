<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Mockery\MockInterface;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\BanTemplate;
use Illuminate\Support\Facades\Bus;
use Pterodactyl\Models\UserSanction;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Pterodactyl\Notifications\AccountBanned;
use Pterodactyl\Notifications\AccountWarned;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Services\Hydra\AutoDeleteService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Services\Hydra\Addons\AddonManager;
use Pterodactyl\Services\Hydra\Users\SanctionService;
use Pterodactyl\Services\Hydra\Addons\AutoDeleteAddon;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\ServerDeletionService;

/**
 * Hydra: warnings, bans, ban templates and the autodelete addon.
 */
class SanctionTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        Notification::fake();
        Bus::fake([RevokeSftpAccessJob::class]);

        // Wings is told about every suspension.
        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('sync');
        });
    }

    public function tearDown(): void
    {
        app(AddonManager::class)->setEnabled(app(AutoDeleteAddon::class), false);

        parent::tearDown();
    }

    private function moderator(array $permissions = ['users.view', 'users.warn', 'users.ban']): User
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Mod ' . uniqid(), 'color' => '#3b9eea', 'permissions' => $permissions]));

        return $user;
    }

    public function testWarningIsMailedAndShownUntilConfirmed(): void
    {
        $mod = $this->moderator();
        $user = User::factory()->create();

        $this->actingAs($mod)->post("/admin/users/view/$user->id/warn", ['reason' => 'Please stop spamming.'])->assertRedirect();

        Notification::assertSentTo($user, AccountWarned::class, fn (AccountWarned $n) => $n->reason === 'Please stop spamming.');
        $warnings = $user->toVueObject()['warnings'];
        $this->assertCount(1, $warnings);
        $this->assertSame('Please stop spamming.', $warnings[0]['reason']);

        // Nobody else can confirm it.
        $this->actingAs(User::factory()->create())->postJson("/api/client/account/warnings/{$warnings[0]['id']}/acknowledge")->assertNotFound();

        $this->actingAs($user)->postJson("/api/client/account/warnings/{$warnings[0]['id']}/acknowledge")
            ->assertOk()
            ->assertJsonPath('warnings', []);
        $this->assertSame([], $user->refresh()->pendingWarnings());
    }

    public function testBanSuspendsServersLogsOutAndBlocksLogin(): void
    {
        $mod = $this->moderator();
        [$user, $server] = $this->generateTestAccount();
        $user->forceFill(['password' => password_hash('secret-password', PASSWORD_BCRYPT)])->save();

        $this->actingAs($mod)->post("/admin/users/view/$user->id/ban", ['reason' => 'Mining on the nodes.', 'duration_days' => 3])->assertRedirect();

        $ban = $user->activeBan();
        $this->assertNotNull($ban);
        $this->assertTrue($ban->expires_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));
        $this->assertSame([$server->id], $ban->suspended_servers);
        $this->assertTrue($server->refresh()->isSuspended());
        $this->assertNotNull($server->hydra_suspended_at);
        Notification::assertSentTo($user, AccountBanned::class);
        Bus::assertDispatched(RevokeSftpAccessJob::class, fn ($job) => $job->user === $user->uuid);

        // Requests of a running session are refused.
        $this->actingAs($user)->getJson('/api/client/account')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'AccountBanned');

        // The login tells why.
        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->postJson('/auth/login', ['user' => $user->username, 'password' => 'secret-password'])
            ->assertStatus(400)
            ->assertSeeText('Mining on the nodes.');

        // Lifting the ban unsuspends the server again.
        $this->actingAs($mod, 'web')->post("/admin/users/view/$user->id/sanctions/$ban->id/lift")->assertRedirect();
        $this->assertNull($user->activeBan());
        $this->assertFalse($server->refresh()->isSuspended());
        $this->assertNull($server->hydra_suspended_at);
    }

    public function testBanFromTemplateAndPermanentBan(): void
    {
        $mod = $this->moderator();
        $user = User::factory()->create();
        $template = BanTemplate::query()->create(['name' => 'Spam', 'reason' => 'Spam in the chat.', 'duration_days' => 7]);

        $this->actingAs($mod)->post("/admin/users/view/$user->id/ban", ['template' => $template->id])->assertRedirect();
        $ban = $user->activeBan();
        $this->assertSame('Spam in the chat.', $ban->reason);
        $this->assertTrue($ban->expires_at->isAfter(now()->addDays(6)));

        // A new ban replaces the old one; "permanent" overrides the template duration.
        $this->actingAs($mod)->post("/admin/users/view/$user->id/ban", ['template' => $template->id, 'permanent' => 1])->assertRedirect();
        $this->assertNull($user->activeBan()->expires_at);
        $this->assertSame(1, $user->sanctions()->activeBans()->count());
        $this->assertNotNull($ban->refresh()->lifted_at);
    }

    public function testNobodyCanBanThemselvesRootAdminsOrHigherAccounts(): void
    {
        $mod = $this->moderator();
        $root = User::factory()->admin()->create();
        $superior = $this->moderator(['users.view', 'users.warn', 'users.ban', 'servers.delete']);

        foreach ([$mod, $root, $superior] as $target) {
            $this->actingAs($mod)->post("/admin/users/view/$target->id/ban", ['reason' => 'x']);
            $this->assertNull($target->activeBan());
        }

        // Without the permission the routes are closed.
        $user = User::factory()->create();
        $this->actingAs($this->moderator(['users.view', 'users.warn']))->post("/admin/users/view/$user->id/ban", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->moderator(['users.view', 'users.ban']))->post("/admin/users/view/$user->id/warn", ['reason' => 'x'])->assertForbidden();
    }

    public function testExpiredBansAreLifted(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $ban = app(SanctionService::class)->ban($user, null, 'Short ban', 1);
        $ban->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertNull($user->activeBan());
        $this->artisan('hydra:autodelete', ['--bans-only' => true])->assertSuccessful();

        $this->assertNotNull($ban->refresh()->lifted_at);
        $this->assertFalse($server->refresh()->isSuspended());
    }

    public function testBanTemplatesCanBeManaged(): void
    {
        $mod = $this->moderator();

        $this->actingAs($mod)->get('/admin/users/ban-templates')->assertOk();
        $this->actingAs($mod)->post('/admin/users/ban-templates', ['name' => 'Cheating', 'reason' => 'Cheats used.', 'duration_days' => 14])->assertRedirect();
        $template = BanTemplate::query()->where('name', 'Cheating')->firstOrFail();

        $this->actingAs($mod)->patch("/admin/users/ban-templates/$template->id", ['name' => 'Cheating', 'reason' => 'Cheats used.', 'duration_days' => null])->assertRedirect();
        $this->assertNull($template->refresh()->duration_days);

        $this->actingAs($mod)->delete("/admin/users/ban-templates/$template->id")->assertRedirect();
        $this->assertNull(BanTemplate::query()->find($template->id));

        $this->actingAs($this->moderator(['users.view', 'users.warn']))->get('/admin/users/ban-templates')->assertForbidden();
    }

    public function testAutoDeleteRemovesOldSuspendedServersAndBannedAccounts(): void
    {
        $addon = app(AutoDeleteAddon::class);
        app(AddonManager::class)->save($addon, ['servers' => true, 'server_days' => 30, 'users' => true, 'user_days' => 14]);
        app(AddonManager::class)->setEnabled($addon, true);

        $old = $this->createServerModel();
        $old->forceFill(['status' => Server::STATUS_SUSPENDED, 'hydra_suspended_at' => now()->subDays(31)])->save();
        $recent = $this->createServerModel();
        $recent->forceFill(['status' => Server::STATUS_SUSPENDED, 'hydra_suspended_at' => now()->subDays(29)])->save();

        $banned = User::factory()->create();
        UserSanction::query()->create(['user_id' => $banned->id, 'type' => 'ban', 'reason' => 'x'])
            ->forceFill(['created_at' => now()->subDays(15)])->save();
        $temporary = User::factory()->create();
        UserSanction::query()->create(['user_id' => $temporary->id, 'type' => 'ban', 'reason' => 'x', 'expires_at' => now()->addDays(30)])
            ->forceFill(['created_at' => now()->subDays(15)])->save();

        $service = app(AutoDeleteService::class);
        $this->assertTrue($service->dueServers()->whereKey($old->id)->exists());
        $this->assertFalse($service->dueServers()->whereKey($recent->id)->exists());
        $this->assertTrue($service->dueUsers()->whereKey($banned->id)->exists());
        $this->assertFalse($service->dueUsers()->whereKey($temporary->id)->exists());

        $this->mock(ServerDeletionService::class, function (MockInterface $mock) use ($old) {
            $mock->allows('withForce')->andReturnSelf();
            $mock->expects('handle')->with(\Mockery::on(fn ($server) => $server->id === $old->id))->andReturnUsing(fn ($server) => $server->delete());
            $mock->allows('handle')->andReturnUsing(fn ($server) => $server->delete());
        });

        $this->artisan('hydra:autodelete')->assertSuccessful();

        $this->assertNull(Server::query()->find($old->id));
        $this->assertNotNull(Server::query()->find($recent->id));
        $this->assertNull(User::query()->find($banned->id));
        $this->assertNotNull(User::query()->find($temporary->id));
    }

    public function testTemplatesWereCreatedByTheMigration(): void
    {
        $this->assertGreaterThanOrEqual(3, BanTemplate::query()->count());
    }
}
