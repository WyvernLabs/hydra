<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\HydraAnnouncement;

/**
 * Hydra: announcements in the client area, written under Design > Announcements.
 */
class AnnouncementTest extends ClientApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        // The table is shared with nothing else, so every test starts empty.
        HydraAnnouncement::query()->delete();
    }

    public function tearDown(): void
    {
        HydraAnnouncement::query()->delete();

        parent::tearDown();
    }

    private function announcement(array $values = []): HydraAnnouncement
    {
        return HydraAnnouncement::query()->create(array_merge([
            'type' => 'info',
            'title' => ['en' => 'Title ' . uniqid()],
            'description' => ['en' => 'Description'],
        ], $values));
    }

    private function form(array $values = []): array
    {
        return array_merge([
            'type' => 'maintenance',
            'title' => ['en' => 'Maintenance', 'de' => 'Wartung'],
            'description' => ['en' => 'On Sunday', 'de' => 'Am Sonntag'],
            'button_text' => ['en' => 'Details', 'de' => ''],
            'button_url' => 'https://status.example.com',
            'scope' => 'general',
            'enabled' => '1',
            'sort' => 3,
        ], $values);
    }

    private function ids(User $user, ?string $server = null): array
    {
        $url = '/api/client/announcements' . ($server ? "?server=$server" : '');

        return array_column($this->actingAs($user)->getJson($url)->assertOk()->json('data'), 'id');
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => $permissions]));

        return $user->refresh();
    }

    public function testDashboardShowsGeneralAndOwnNodesButNoOtherOrDisabledOnes(): void
    {
        $user = User::factory()->create();
        $server = $this->createServerModel(['user_id' => $user->id]);
        $otherNode = Node::factory()->create(['location_id' => $server->node->location_id]);

        $general = $this->announcement();
        $own = $this->announcement(['nodes' => [$server->node_id]]);
        $this->announcement(['nodes' => [$otherNode->id]]);
        $this->announcement(['enabled' => false]);

        $this->assertEqualsCanonicalizing([$general->id, $own->id], $this->ids($user));
    }

    public function testUserWithoutServersOnlySeesGeneralOnes(): void
    {
        $server = $this->createServerModel();
        $general = $this->announcement();
        $this->announcement(['nodes' => [$server->node_id]]);

        $this->assertSame([$general->id], $this->ids(User::factory()->create()));
    }

    public function testSubusersSeeTheNodeOfTheServer(): void
    {
        $server = $this->createServerModel();
        $own = $this->announcement(['nodes' => [$server->node_id]]);
        $subuser = User::factory()->create();
        Subuser::query()->create(['user_id' => $subuser->id, 'server_id' => $server->id, 'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT]]);

        $this->assertSame([$own->id], $this->ids($subuser));
        $this->assertSame([$own->id], $this->ids($subuser, $server->uuid));
    }

    public function testServerPageOnlyShowsItsNodeAndOnlyToUsersOfTheServer(): void
    {
        $user = User::factory()->create();
        $server = $this->createServerModel(['user_id' => $user->id]);
        $this->announcement();
        $own = $this->announcement(['nodes' => [$server->node_id]]);

        $this->assertSame([$own->id], $this->ids($user, $server->uuid));
        $this->assertSame([], $this->ids(User::factory()->create(), $server->uuid));
        $this->assertSame([$own->id], $this->ids($this->userWith(['servers.access']), $server->uuid));
    }

    public function testTextsComeInTheLanguageOfTheUserWithAFallback(): void
    {
        $user = User::factory()->create(['language' => 'de']);
        $this->announcement([
            'type' => 'maintenance',
            'title' => ['en' => 'Maintenance', 'de' => 'Wartung'],
            'description' => ['en' => 'Only english'],
            'button_text' => ['en' => 'Details', 'de' => 'Mehr erfahren'],
            'button_url' => '/account',
        ]);

        $this->actingAs($user)->getJson('/api/client/announcements')->assertOk()
            ->assertJsonPath('data.0.type', 'maintenance')
            ->assertJsonPath('data.0.title', 'Wartung')
            ->assertJsonPath('data.0.description', 'Only english')
            ->assertJsonPath('data.0.button.text', 'Mehr erfahren')
            ->assertJsonPath('data.0.button.url', '/account');
    }

    public function testClosingHidesItForThatUserFor24Hours(): void
    {
        $user = User::factory()->create();
        $item = $this->announcement(['dismissible' => true]);

        $this->actingAs($user)->getJson('/api/client/announcements')->assertOk()->assertJsonPath('data.0.dismissible', true);
        $this->actingAs($user)->postJson("/api/client/announcements/$item->id/dismiss")->assertNoContent();

        $this->assertSame([], $this->ids($user));
        $this->assertSame([$item->id], $this->ids(User::factory()->create()), 'Other users still see it.');

        $this->travelAll(23 * 60 + 59);
        $this->assertSame([], $this->ids($user));

        $this->travelAll(2);
        $this->assertSame([$item->id], $this->ids($user), 'It is back after 24 hours.');

        // Closing again starts over and does not add a second row.
        $this->actingAs($user)->postJson("/api/client/announcements/$item->id/dismiss")->assertNoContent();
        $this->assertSame([], $this->ids($user));
        $this->assertSame(1, DB::table('hydra_announcement_dismissals')->where('user_id', $user->id)->count());
    }

    public function testOnlyDismissibleAndVisibleOnesCanBeClosed(): void
    {
        $user = User::factory()->create();
        $fixed = $this->announcement();
        $off = $this->announcement(['dismissible' => true, 'enabled' => false]);
        $expired = $this->announcement(['dismissible' => true, 'expires_at' => now()->subMinute()]);

        foreach ([$fixed, $off, $expired] as $item) {
            $this->actingAs($user)->postJson("/api/client/announcements/$item->id/dismiss")->assertNotFound();
        }
        $this->actingAs($user)->postJson('/api/client/announcements/999999/dismiss')->assertNotFound();
        $this->assertSame(0, DB::table('hydra_announcement_dismissals')->where('user_id', $user->id)->count());
    }

    public function testAnnouncementIsBackWhenClosingIsSwitchedOff(): void
    {
        $user = User::factory()->create();
        $item = $this->announcement(['dismissible' => true]);

        $this->actingAs($user)->postJson("/api/client/announcements/$item->id/dismiss")->assertNoContent();
        $this->assertSame([], $this->ids($user));

        $item->update(['dismissible' => false]);
        $this->assertSame([$item->id], $this->ids($user));
    }

    public function testExpiredAnnouncementsAreGoneAndMarkedInTheAdminArea(): void
    {
        $user = User::factory()->create();
        $item = $this->announcement(['expires_at' => now()->addHour()]);

        $this->assertSame([$item->id], $this->ids($user));

        $this->travelAll(61);
        $this->assertSame([], $this->ids($user));

        // The web guard again after the API requests.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->actingAs(User::factory()->admin()->create())->get('/admin/design/announcements')->assertOk()
            ->assertSee(__('admin/announcements.expired'));
    }

    public function testOneLanguageIsEnoughWhicheverItIs(): void
    {
        config()->set('app.locale', 'en');

        $this->actingAs(User::factory()->admin()->create())->post('/admin/design/announcements/new', $this->form([
            'title' => ['de' => 'Nur Deutsch', 'en' => ''],
            'description' => ['de' => 'Nur Deutsch', 'en' => ''],
            'button_text' => [],
            'button_url' => '',
        ]))->assertRedirect('/admin/design/announcements');

        $this->assertSame(['de' => 'Nur Deutsch'], HydraAnnouncement::query()->firstOrFail()->title);
        $this->assertSame('Nur Deutsch', HydraAnnouncement::query()->firstOrFail()->toClientArray()['title']);
    }

    public function testAdminSetsClosingAndExpiry(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/announcements/new', $this->form(['dismissible' => '1', 'expires_at' => '2030-05-01T18:30']))
            ->assertRedirect('/admin/design/announcements');

        $item = HydraAnnouncement::query()->firstOrFail();
        $this->assertTrue($item->dismissible);
        $this->assertSame('2030-05-01 18:30', $item->expires_at->format('Y-m-d H:i'));
        $this->actingAs($admin)->get("/admin/design/announcements/$item->id")->assertOk()->assertSee('2030-05-01T18:30');

        $this->actingAs($admin)->patch("/admin/design/announcements/$item->id", $this->form(['dismissible' => '0', 'expires_at' => '']))->assertRedirect();
        $item->refresh();
        $this->assertFalse($item->dismissible);
        $this->assertNull($item->expires_at);

        $this->actingAs($admin)->post('/admin/design/announcements/new', $this->form(['expires_at' => 'soon']))
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'expires_at');
    }

    /**
     * Moves the clock of both Carbon classes the panel uses.
     */
    private function travelAll(int $minutes): void
    {
        $now = Carbon::now()->addMinutes($minutes);
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    public function testAnnouncementsNeedTheirPermissions(): void
    {
        $item = $this->announcement();
        $viewer = $this->userWith(['announcements.view', 'design.view']);

        $this->actingAs($viewer)->get('/admin/design/announcements')->assertOk()->assertSee($item->title['en']);
        $this->actingAs($viewer)->get("/admin/design/announcements/$item->id")->assertOk();
        $this->actingAs($viewer)->post('/admin/design/announcements/new', $this->form())->assertForbidden();
        $this->actingAs($viewer)->delete("/admin/design/announcements/$item->id")->assertForbidden();

        $this->actingAs($this->userWith(['design.view']))->get('/admin/design/announcements')->assertForbidden();
    }

    public function testAdminCreatesEditsAndDeletes(): void
    {
        $admin = User::factory()->admin()->create();
        $node = $this->createServerModel()->node;

        $this->actingAs($admin)->get('/admin/design/announcements/new')->assertOk();
        $this->actingAs($admin)->post('/admin/design/announcements/new', $this->form())->assertRedirect('/admin/design/announcements');

        $item = HydraAnnouncement::query()->firstOrFail();
        $this->assertSame('maintenance', $item->type);
        $this->assertSame(['en' => 'Details'], $item->button_text);
        $this->assertNull($item->nodes);
        $this->assertSame(3, $item->sort);
        $this->assertTrue($item->enabled);

        $this->actingAs($admin)->patch("/admin/design/announcements/$item->id", $this->form([
            'type' => 'critical', 'scope' => 'nodes', 'nodes' => [(string) $node->id], 'enabled' => '0', 'button_text' => [], 'button_url' => '',
        ]))->assertRedirect('/admin/design/announcements');

        $item->refresh();
        $this->assertSame('critical', $item->type);
        $this->assertSame([$node->id], $item->nodes);
        $this->assertNull($item->button_url);
        $this->assertFalse($item->enabled);

        $this->actingAs($admin)->get('/admin/design/announcements')->assertOk()->assertSee($node->name);
        $this->actingAs($admin)->get("/admin/design/announcements/$item->id")->assertOk();
        $this->actingAs($admin)->delete("/admin/design/announcements/$item->id")->assertRedirect('/admin/design/announcements');
        $this->assertSame(0, HydraAnnouncement::query()->count());
    }

    public function testInvalidAnnouncementsAreRejected(): void
    {
        $admin = User::factory()->admin()->create();
        $invalid = [
            'type' => ['type' => 'other'],
            'title' => ['title' => ['en' => '']],
            'description' => ['description' => ['en' => '']],
            'nodes' => ['scope' => 'nodes', 'nodes' => []],
            'button_url' => ['button_url' => 'javascript:alert(1)'],
        ];

        foreach ($invalid as $field => $values) {
            $response = $this->actingAs($admin)->post('/admin/design/announcements/new', $this->form($values))->assertStatus(422);
            $this->assertStringStartsWith($field, $response->json('errors.0.meta.source_field'));
        }

        foreach (['//example.com', 'data:text/html,x', 'example.com'] as $url) {
            $this->actingAs($admin)->post('/admin/design/announcements/new', $this->form(['button_url' => $url]))->assertStatus(422);
        }

        $this->actingAs($admin)->post('/admin/design/announcements/new', $this->form(['button_url' => '']))
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'button_url');
        $this->actingAs($admin)->post('/admin/design/announcements/new', $this->form(['button_text' => []]))
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'button_text');

        $this->assertSame(0, HydraAnnouncement::query()->count());
    }
}
