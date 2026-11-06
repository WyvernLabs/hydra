<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\AdminRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\HydraStatusService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Services\Hydra\Status\StatusPageService;

/**
 * Hydra: the public status page (addon "status").
 */
class StatusPageTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        config()->set('hydra.addons.status.enabled', true);
        DB::table('hydra_status_checks')->delete();
        HydraStatusService::query()->delete();
    }

    public function tearDown(): void
    {
        DB::table('hydra_status_checks')->delete();
        HydraStatusService::query()->delete();

        parent::tearDown();
    }

    private function node(array $values = []): Node
    {
        return Node::factory()->create($values + ['location_id' => Location::factory()->create()->id, 'name' => 'Node ' . uniqid()]);
    }

    private function service(array $values = []): HydraStatusService
    {
        return HydraStatusService::query()->create($values + ['name' => 'App ' . uniqid(), 'type' => 'http', 'target' => 'https://secret-target.example.com']);
    }

    private function results(string $target, int $hoursAgo, int $checks, int $failures, bool $online = true): void
    {
        DB::table('hydra_status_checks')->insert([
            'target' => $target,
            'hour' => now()->startOfHour()->subHours($hoursAgo)->format('Y-m-d H:i:s'),
            'checks' => $checks,
            'failures' => $failures,
            'online' => $online,
        ]);
    }

    private function row(string $target): ?object
    {
        return DB::table('hydra_status_checks')->where('target', $target)->first();
    }

    public function testPageIsGoneWhileTheAddonIsOff(): void
    {
        config()->set('hydra.addons.status.enabled', false);

        $this->get('/status')->assertNotFound();
    }

    public function testVisitorsSeeNamesButNoAddressesAndNoPrivateNodes(): void
    {
        $public = $this->node(['fqdn' => 'secret-node.example.com']);
        $private = $this->node(['public' => false]);
        $service = $this->service();

        $this->get('/status')->assertOk()
            ->assertSee($public->name)
            ->assertSee($service->name)
            ->assertDontSee($private->name)
            ->assertDontSee('secret-node.example.com')
            ->assertDontSee('secret-target.example.com')
            ->assertSee('Hydra by WyvernLabs', false);

        config()->set('hydra.addons.status.private_nodes', true);
        $this->get('/status')->assertOk()->assertSee($private->name);
    }

    public function testEveryEntryHasOneBarPerHourForSevenDays(): void
    {
        $service = $this->service();
        $key = $service->statusKey();
        $this->results($key, 0, 30, 0);
        $this->results($key, 1, 60, 0);
        $this->results($key, 2, 60, 3);
        $this->results($key, 3, 60, 60);
        $this->results($key, 200, 60, 60);

        $entry = collect($this->app->make(StatusPageService::class)->page()['services'])->firstWhere('name', $service->name);
        $this->assertCount(168, $entry['bars']);

        $states = array_column($entry['bars'], 'state');
        $this->assertSame('online', $states[167], 'Newest hour on the right.');
        $this->assertSame('online', $states[166]);
        $this->assertSame('partial', $states[165]);
        $this->assertSame('offline', $states[164]);
        $this->assertSame('unknown', $states[163]);
        $this->assertSame('unknown', $states[0], 'Older than 7 days is not shown.');

        // 210 checks with 63 failures; the 8 days old hour does not count.
        $this->assertSame(70.0, $entry['uptime']);
        $this->assertSame('online', $entry['state']);
    }

    public function testCurrentStateAndOverallFollowTheLastCheck(): void
    {
        $service = $this->service();
        $page = fn () => $this->app->make(StatusPageService::class)->page();
        $entry = fn () => collect($page()['services'])->firstWhere('name', $service->name);

        $this->assertSame('unknown', $entry()['state']);

        $this->results($service->statusKey(), 0, 5, 1, false);
        $this->assertSame('offline', $entry()['state']);
        $this->assertSame('offline', $page()['overall']);

        // Results from hours ago do not say anything about now.
        DB::table('hydra_status_checks')->delete();
        $this->results($service->statusKey(), 5, 60, 0);
        $this->assertSame('unknown', $entry()['state']);
    }

    public function testChecksCountPerHourAndFollowTheResponses(): void
    {
        $up = $this->node(['fqdn' => 'up.example.com', 'scheme' => 'https']);
        $broken = $this->node(['fqdn' => 'broken.example.com', 'scheme' => 'https']);
        $login = $this->service(['target' => 'https://login.example.com']);
        $error = $this->service(['target' => 'https://error.example.com']);
        $off = $this->service(['target' => 'https://off.example.com', 'enabled' => false]);

        Http::fake([
            'broken.example.com*' => Http::response('', 500),
            'login.example.com*' => Http::response('', 401),
            'error.example.com*' => Http::response('', 503),
            '*' => Http::response('{}', 200),
        ]);

        $this->artisan('hydra:status:check')->assertSuccessful();
        $this->artisan('hydra:status:check')->assertSuccessful();

        $this->assertSame(2, (int) $this->row("node:$up->id")->checks);
        $this->assertSame(0, (int) $this->row("node:$up->id")->failures);
        $this->assertSame(2, (int) $this->row("node:$broken->id")->failures);
        $this->assertSame(0, (int) $this->row($login->statusKey())->failures, 'An application asking for a login is up.');
        $this->assertSame(2, (int) $this->row($error->statusKey())->failures);
        $this->assertNull($this->row($off->statusKey()));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'up.example.com') && $request->hasHeader('Authorization'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'login.example.com') && $request->hasHeader('Authorization'));
    }

    public function testUnreachableTargetsCountAsOffline(): void
    {
        $service = $this->service(['target' => 'https://down.example.com']);
        Http::fake(['down.example.com*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down'), '*' => Http::response('{}', 200)]);

        $this->artisan('hydra:status:check')->assertSuccessful();

        $this->assertSame(1, (int) $this->row($service->statusKey())->failures);
    }

    public function testTcpChecksConnectToThePort(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $open = $this->service(['type' => 'tcp', 'target' => "127.0.0.1:$port"]);
        // A port that was free a moment ago.
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $closedPort = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $closed = $this->service(['type' => 'tcp', 'target' => "127.0.0.1:$closedPort"]);

        $this->artisan('hydra:status:check')->assertSuccessful();
        fclose($server);

        $this->assertSame(0, (int) $this->row($open->statusKey())->failures);
        $this->assertSame(1, (int) $this->row($closed->statusKey())->failures);
    }

    public function testOldResultsAreRemovedAndNothingRunsWhileTheAddonIsOff(): void
    {
        Http::fake(['*' => Http::response('{}', 200)]);
        $service = $this->service();
        $this->results($service->statusKey(), 24 * 9, 60, 0);

        config()->set('hydra.addons.status.enabled', false);
        $this->artisan('hydra:status:check')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertNotNull($this->row($service->statusKey()));

        config()->set('hydra.addons.status.enabled', true);
        $this->artisan('hydra:status:check')->assertSuccessful();
        $this->assertSame(1, DB::table('hydra_status_checks')->where('target', $service->statusKey())->count());
    }

    public function testAdminManagesApplications(): void
    {
        $admin = User::factory()->create(['root_admin' => true]);
        $form = ['name' => 'Webseite', 'type' => 'http', 'target' => 'https://example.com', 'enabled' => '1', 'sort' => 2];

        $this->actingAs($admin)->get('/admin/design/addons/status')->assertOk();
        $this->actingAs($admin)->post('/admin/design/status', $form)->assertRedirect('/admin/design/status');
        $service = HydraStatusService::query()->firstOrFail();
        $this->assertSame(['Webseite', 'http', 2, true], [$service->name, $service->type, $service->sort, $service->enabled]);

        $this->actingAs($admin)->get('/admin/design/status')->assertOk()->assertSee('Webseite');
        $this->actingAs($admin)->get("/admin/design/status/$service->id")->assertOk()->assertSee('https://example.com');

        $this->actingAs($admin)->patch("/admin/design/status/$service->id", ['name' => 'Datenbank', 'type' => 'tcp', 'target' => 'db.example.com:3306'])->assertRedirect();
        $service->refresh();
        $this->assertSame(['Datenbank', 'tcp', 'db.example.com:3306', false], [$service->name, $service->type, $service->target, $service->enabled]);

        $this->results($service->statusKey(), 0, 1, 0);
        $this->actingAs($admin)->delete("/admin/design/status/$service->id")->assertRedirect();
        $this->assertNull(HydraStatusService::query()->find($service->id));
        $this->assertNull($this->row($service->statusKey()));
    }

    public function testInvalidTargetsAreRejected(): void
    {
        $admin = User::factory()->create(['root_admin' => true]);
        $cases = [
            ['http', 'example.com'], ['http', 'ftp://example.com'], ['http', 'javascript:alert(1)'],
            ['tcp', 'example.com'], ['tcp', 'example.com:0'], ['tcp', 'example.com:70000'], ['tcp', 'https://example.com:443'],
        ];

        foreach ($cases as [$type, $target]) {
            $this->actingAs($admin)->post('/admin/design/status', ['name' => 'X', 'type' => $type, 'target' => $target])
                ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'target');
        }
        $this->assertSame(0, HydraStatusService::query()->count());
    }

    public function testApplicationsNeedTheAddonPermissions(): void
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => ['design.view', 'addons.view']]));
        $user->refresh();

        $this->actingAs($user)->get('/admin/design/status')->assertOk();
        $this->actingAs($user)->post('/admin/design/status', ['name' => 'X', 'type' => 'http', 'target' => 'https://example.com'])->assertForbidden();
    }
}
