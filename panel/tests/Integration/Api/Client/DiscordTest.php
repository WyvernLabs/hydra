<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\AdminRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Request;
use Pterodactyl\Models\HydraDiscordLink;
use Pterodactyl\Services\Hydra\Tickets\TicketService;

/**
 * Hydra: connecting accounts to Discord, roles and messages (addon "discord").
 */
class DiscordTest extends ClientApiIntegrationTestCase
{
    private const GUILD = '100000000000000001';
    private const VERIFIED = '200000000000000001';
    private const HAS_SERVER = '200000000000000002';
    private const ADMIN_ROLE = '200000000000000003';
    private const NODE_ROLE = '200000000000000004';
    private const FOREIGN_ROLE = '200000000000000099';

    private array $memberRoles = [];

    private ?int $memberStatus = null;

    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        config()->set('hydra.addons.discord', [
            'enabled' => true,
            'bot_token' => 'test-token',
            'guild_id' => self::GUILD,
            'role_verified' => self::VERIFIED,
            'role_server' => self::HAS_SERVER,
            'role_map' => [],
            'node_map' => [],
            'ticket_notifications' => true,
        ]);
        config()->set('hydra.addons.tickets.enabled', true);
        Cache::flush();

        $this->memberRoles = [self::FOREIGN_ROLE];
        $this->memberStatus = null;

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/users/@me/channels')) {
                return Http::response(['id' => '300000000000000001', 'recipients' => [['username' => 'someone']]]);
            }
            if (str_contains($url, '/channels/')) {
                return Http::response(['id' => '1']);
            }
            if ($request->method() === 'GET' && str_contains($url, '/members/')) {
                return $this->memberStatus === 404
                    ? Http::response(['code' => 10007, 'message' => 'Unknown Member'], 404)
                    : Http::response(['roles' => $this->memberRoles]);
            }

            return Http::response('', 204);
        });
    }

    private function discordId(): string
    {
        return '4' . str_pad((string) random_int(0, 999999999999999999), 18, '0', STR_PAD_LEFT);
    }

    private function lastMessage(): ?string
    {
        $messages = Http::recorded(fn (Request $request) => str_contains($request->url(), '/channels/') && !str_contains($request->url(), '@me'));

        return $messages->last()[0]['content'] ?? null;
    }

    private function codeFromMessage(): string
    {
        preg_match('/`([A-Z0-9]+)`/', (string) $this->lastMessage(), $match);

        return $match[1];
    }

    private function roleCalls(string $method): array
    {
        return Http::recorded(fn (Request $request) => $request->method() === $method && str_contains($request->url(), '/roles/'))
            ->map(fn ($pair) => substr(strrchr($pair[0]->url(), '/'), 1))
            ->values()
            ->all();
    }

    private function linkAccount(User $user, array $values = []): HydraDiscordLink
    {
        return HydraDiscordLink::query()->create($values + ['user_id' => $user->id, 'discord_id' => $this->discordId()]);
    }

    private function travel_(int $seconds): void
    {
        $now = Carbon::now()->addSeconds($seconds);
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    public function testAccountEndpointsAreGoneWhileTheAddonIsOff(): void
    {
        config()->set('hydra.addons.discord.enabled', false);

        $this->actingAs(User::factory()->create())->getJson('/api/client/account/discord')->assertNotFound();
    }

    public function testLinkingWithTheCodeFromTheBot(): void
    {
        $user = User::factory()->create();
        $id = $this->discordId();

        $this->actingAs($user)->getJson('/api/client/account/discord')->assertOk()->assertJsonPath('link', null)->assertJsonPath('pending', null);

        $this->actingAs($user)->postJson('/api/client/account/discord/code', ['discord_id' => $id])
            ->assertOk()->assertJsonPath('pending.discord_id', $id)->assertJsonPath('link', null);

        // The code only goes to Discord, the panel does not reveal it.
        $code = $this->codeFromMessage();
        $this->assertSame(8, strlen($code));
        $this->assertStringContainsString($user->username, $this->lastMessage());
        $this->assertStringNotContainsString($code, json_encode($this->actingAs($user)->getJson('/api/client/account/discord')->json()));
        $this->assertNull(HydraDiscordLink::query()->where('user_id', $user->id)->first());

        $this->actingAs($user)->postJson('/api/client/account/discord/confirm', ['code' => strtolower($code)])
            ->assertOk()->assertJsonPath('link.discord_id', $id)->assertJsonPath('link.username', 'someone')->assertJsonPath('pending', null);

        $link = HydraDiscordLink::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($id, $link->discord_id);
        $this->assertSame([self::VERIFIED], $this->roleCalls('PUT'));
    }

    public function testCodeRunsOutAfterFiveMinutes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/client/account/discord/code', ['discord_id' => $this->discordId()])->assertOk();
        $code = $this->codeFromMessage();

        $this->travel_(5 * 60 + 1);

        $this->actingAs($user)->postJson('/api/client/account/discord/confirm', ['code' => $code])->assertStatus(400)
            ->assertJsonPath('errors.0.detail', __('discord.errors.code_expired'));
        $this->assertNull(HydraDiscordLink::query()->where('user_id', $user->id)->first());
    }

    public function testCodeStillWorksJustBeforeFiveMinutes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/client/account/discord/code', ['discord_id' => $this->discordId()])->assertOk();
        $code = $this->codeFromMessage();

        $this->travel_(4 * 60 + 50);

        $this->actingAs($user)->postJson('/api/client/account/discord/confirm', ['code' => $code])->assertOk();
    }

    public function testWrongCodesAreLimitedAndACodeOnlyWorksForItsUser(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/client/account/discord/code', ['discord_id' => $this->discordId()])->assertOk();
        $code = $this->codeFromMessage();

        $this->actingAs(User::factory()->create())->postJson('/api/client/account/discord/confirm', ['code' => $code])->assertStatus(400);

        for ($i = 0; $i < 4; ++$i) {
            $this->actingAs($user)->postJson('/api/client/account/discord/confirm', ['code' => 'WRONG'])->assertStatus(400)
                ->assertJsonPath('errors.0.detail', __('discord.errors.code_wrong'));
        }
        $this->actingAs($user)->postJson('/api/client/account/discord/confirm', ['code' => 'WRONG'])->assertStatus(400)
            ->assertJsonPath('errors.0.detail', __('discord.errors.too_many_attempts'));

        // Even the right code is useless now.
        $this->actingAs($user)->postJson('/api/client/account/discord/confirm', ['code' => $code])->assertStatus(400)
            ->assertJsonPath('errors.0.detail', __('discord.errors.code_expired'));
    }

    public function testADiscordAccountBelongsToOnePanelAccount(): void
    {
        $taken = $this->linkAccount(User::factory()->create());

        $this->actingAs(User::factory()->create())->postJson('/api/client/account/discord/code', ['discord_id' => $taken->discord_id])
            ->assertStatus(400)->assertJsonPath('errors.0.detail', __('discord.errors.taken'));
        $this->assertNull($this->lastMessage());
    }

    public function testADiscordAccountGetsOneCodePerMinuteWhoeverAsks(): void
    {
        $id = $this->discordId();
        $this->actingAs(User::factory()->create())->postJson('/api/client/account/discord/code', ['discord_id' => $id])->assertOk();
        $this->actingAs(User::factory()->create())->postJson('/api/client/account/discord/code', ['discord_id' => $id])->assertStatus(400);

        $this->assertCount(1, Http::recorded(fn (Request $request) => str_contains($request->url(), '/messages')));
    }

    public function testInvalidIdsAndClosedDirectMessages(): void
    {
        $user = User::factory()->create();
        foreach (['abc', '123', '1234567890123456789012'] as $id) {
            $this->actingAs($user)->postJson('/api/client/account/discord/code', ['discord_id' => $id])->assertStatus(422);
        }

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*/users/@me/channels' => Http::response(['id' => '3']), '*' => Http::response(['code' => 50007, 'message' => 'Cannot send messages to this user'], 403)]);
        $this->actingAs($user)->postJson('/api/client/account/discord/code', ['discord_id' => $this->discordId()])
            ->assertStatus(400)->assertJsonPath('errors.0.detail', __('discord.errors.dm_closed'));
        $this->actingAs($user)->getJson('/api/client/account/discord')->assertJsonPath('pending', null);
    }

    public function testRolesFollowServersPanelRolesAndNodes(): void
    {
        $user = User::factory()->create();
        $server = $this->createServerModel(['user_id' => $user->id]);
        $role = AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => []]);
        $user->adminRoles()->attach($role);
        config()->set('hydra.addons.discord.role_map', [$role->id => self::ADMIN_ROLE]);
        config()->set('hydra.addons.discord.node_map', [$server->node_id => self::NODE_ROLE]);
        $this->linkAccount($user);

        $this->artisan('hydra:discord:sync')->assertSuccessful();

        $this->assertEqualsCanonicalizing([self::VERIFIED, self::HAS_SERVER, self::ADMIN_ROLE, self::NODE_ROLE], $this->roleCalls('PUT'));
        $this->assertSame([], $this->roleCalls('DELETE'), 'Roles the panel does not manage stay.');
    }

    public function testRolesAreTakenAwayAgainButOthersStay(): void
    {
        $user = User::factory()->create();
        $link = $this->linkAccount($user, ['roles' => ['200000000000000050']]);
        $this->memberRoles = [self::FOREIGN_ROLE, self::VERIFIED, self::HAS_SERVER, '200000000000000050'];

        $this->artisan('hydra:discord:sync')->assertSuccessful();

        // No server any more, and a role that is no longer in the settings.
        $this->assertEqualsCanonicalizing([self::HAS_SERVER, '200000000000000050'], $this->roleCalls('DELETE'));
        $this->assertSame([self::VERIFIED], $link->refresh()->roles);
    }

    public function testUsersWhoAreNotOnTheServerAreSkipped(): void
    {
        $this->memberStatus = 404;
        $link = $this->linkAccount(User::factory()->create());

        $this->artisan('hydra:discord:sync')->assertSuccessful();

        $this->assertSame([], $this->roleCalls('PUT'));
        $this->assertNull($link->refresh()->roles);
    }

    public function testUnlinkingRemovesTheRoles(): void
    {
        $user = User::factory()->create();
        $this->linkAccount($user, ['roles' => [self::VERIFIED]]);
        $this->memberRoles = [self::VERIFIED, self::FOREIGN_ROLE];

        $this->actingAs($user)->deleteJson('/api/client/account/discord')->assertNoContent();

        $this->assertSame([self::VERIFIED], $this->roleCalls('DELETE'));
        $this->assertNull(HydraDiscordLink::query()->where('user_id', $user->id)->first());
    }

    public function testNotificationsCanBeSwitchedOff(): void
    {
        $user = User::factory()->create();
        $this->linkAccount($user);

        $this->actingAs($user)->patchJson('/api/client/account/discord', ['notify_tickets' => false])->assertOk()->assertJsonPath('link.notify_tickets', false);
        $this->assertFalse(HydraDiscordLink::query()->where('user_id', $user->id)->firstOrFail()->notify_tickets);
    }

    private function ticket(User $owner, array $values = []): Ticket
    {
        return Ticket::query()->create($values + ['user_id' => $owner->id, 'subject' => 'Printer on fire', 'status' => Ticket::STATUS_OPEN]);
    }

    private function messages(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), '/messages'))->count();
    }

    public function testOwnerHearsAboutAnswersOfTheTeam(): void
    {
        $owner = User::factory()->create();
        $this->linkAccount($owner);
        $staff = User::factory()->admin()->create();
        $ticket = $this->ticket($owner);
        $service = $this->app->make(TicketService::class);

        $service->reply($ticket, $staff, 'Internal note', true, true);
        $this->app->terminate();
        $this->assertSame(0, $this->messages(), 'Internal notes stay internal.');

        $service->reply($ticket, $staff, 'We are on it', true);
        $this->app->terminate();
        $this->assertSame(1, $this->messages());
        $this->assertStringContainsString('Printer on fire', $this->lastMessage());
        $this->assertStringContainsString("/tickets/$ticket->id", $this->lastMessage());
        $this->assertStringNotContainsString('We are on it', $this->lastMessage());
    }

    public function testAssignedTeamMemberHearsAboutAnswersOfTheOwner(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->admin()->create();
        $this->linkAccount($owner);
        $this->linkAccount($staff);
        $ticket = $this->ticket($owner, ['assigned_to' => $staff->id]);
        $ticket->forceFill(['assigned_to' => $staff->id])->save();

        $this->app->make(TicketService::class)->reply($ticket->refresh(), $owner, 'Any news?', false);
        $this->app->terminate();

        $this->assertSame(1, $this->messages());
        $this->assertStringContainsString("/admin/tickets/view/$ticket->id", $this->lastMessage());
    }

    public function testNoMessageWithTheOptionOffOrWithoutALink(): void
    {
        $staff = User::factory()->admin()->create();
        $service = $this->app->make(TicketService::class);

        $service->reply($this->ticket(User::factory()->create()), $staff, 'No link', true);

        $quiet = User::factory()->create();
        $this->linkAccount($quiet, ['notify_tickets' => false]);
        $service->reply($this->ticket($quiet), $staff, 'Switched off', true);

        $owner = User::factory()->create();
        $this->linkAccount($owner);
        config()->set('hydra.addons.discord.ticket_notifications', false);
        $service->reply($this->ticket($owner), $staff, 'Addon option off', true);

        $this->app->terminate();
        $this->assertSame(0, $this->messages());
    }

    public function testDiscordBeingDownDoesNotBreakATicketAnswer(): void
    {
        $owner = User::factory()->create();
        $this->linkAccount($owner);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['message' => 'Bad gateway'], 502)]);

        $message = $this->app->make(TicketService::class)->reply($this->ticket($owner), User::factory()->admin()->create(), 'Answer', true);
        $this->app->terminate();

        $this->assertNotNull($message->id);
    }

    public function testSettingsKeepTheTokenSecret(): void
    {
        $admin = User::factory()->admin()->create();

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->actingAs($admin)->get('/admin/design/addons/discord')->assertOk()->assertDontSee('test-token');

        $this->actingAs($admin)->patch('/admin/design/addons/discord', [
            'enabled' => '1', 'bot_token' => '', 'guild_id' => 'not-a-number',
        ])->assertStatus(422);

        $this->actingAs($admin)->patch('/admin/design/addons/discord', [
            'enabled' => '1', 'bot_token' => 'new-secret-token', 'guild_id' => self::GUILD,
            'role_verified' => self::VERIFIED, 'role_server' => '',
            'role_map' => ['root' => self::ADMIN_ROLE], 'node_map' => [],
            'ticket_notifications' => '1',
        ])->assertRedirect();

        $stored = \Pterodactyl\Models\Setting::query()->where('key', 'settings::hydra:addons:discord:bot_token')->value('value');
        $this->assertNotSame('new-secret-token', $stored);
        $this->assertSame('new-secret-token', decrypt($stored));
        $this->assertSame(['root' => self::ADMIN_ROLE], config('hydra.addons.discord.role_map'));

        \Pterodactyl\Models\Setting::query()->where('key', 'like', 'settings::hydra:addons:discord:%')->delete();
    }

    public function testConnectionTestFindsMissingRightsAndRoleOrder(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            '*/users/@me' => Http::response(['id' => '500000000000000001', 'username' => 'HydraBot']),
            '*/guilds/' . self::GUILD => Http::response(['name' => 'My Discord']),
            '*/guilds/' . self::GUILD . '/roles' => Http::response([
                ['id' => '600000000000000001', 'name' => 'bot', 'position' => 2, 'permissions' => '0'],
                ['id' => self::VERIFIED, 'name' => 'Verified', 'position' => 5, 'permissions' => '0'],
            ]),
            '*/guilds/' . self::GUILD . '/members/*' => Http::response(['roles' => ['600000000000000001']]),
        ]);

        $result = $this->app->make(\Pterodactyl\Services\Hydra\Discord\DiscordDiagnostics::class)->run();

        $this->assertStringContainsString('HydraBot', $result['ok'][0]);
        $this->assertStringContainsString('My Discord', $result['ok'][1]);
        $this->assertCount(3, $result['problems'], 'No right to manage roles, Verified above the bot, unknown role.');
    }
}
