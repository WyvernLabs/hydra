<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\AdminRole;
use Illuminate\Support\Facades\File;
use GuzzleHttp\Exception\ClientException;
use Pterodactyl\Services\Hydra\DomainService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Repositories\Wings\DaemonRemoteRepository;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: change the domain of the panel from the admin area.
 */
class DomainControllerTest extends HttpTestCase
{
    private string $dir;

    public function setUp(): void
    {
        parent::setUp();

        $this->dir = DomainService::directory();
        File::deleteDirectory($this->dir);
        File::ensureDirectoryExists($this->dir);
    }

    public function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function installed(array $redirects = []): void
    {
        file_put_contents("$this->dir/current.json", json_encode([
            'domain' => 'www.example.com',
            'ssl' => true,
            'url' => 'https://www.example.com',
            'redirects' => $redirects,
        ]));
    }

    private function request(): array
    {
        return json_decode((string) file_get_contents("$this->dir/request"), true);
    }

    public function testNormalizeAndValidate(): void
    {
        $this->assertSame('panel.example.com', DomainService::normalize(' https://Panel.Example.com/admin '));
        $this->assertSame('panel.example.com', DomainService::normalize('panel.example.com.'));

        foreach (['panel.example.com', 'a.b', '203.0.113.10', 'x-1.example.de'] as $valid) {
            $this->assertTrue(DomainService::isValid($valid), $valid);
        }
        foreach (['', 'localhost', '-x.example.com', 'a b.de', '999.1.1.1', 'panel.example.com;rm', "panel.example.com\nx"] as $invalid) {
            $this->assertFalse(DomainService::isValid($invalid), $invalid);
        }

        $this->assertSame('https://panel.example.com', DomainService::url('panel.example.com', 'auto'));
        $this->assertSame('http://203.0.113.10', DomainService::url('203.0.113.10', 'auto'));
        $this->assertSame('http://panel.example.com', DomainService::url('panel.example.com', 'off'));
    }

    public function testRequestsNeedTheHelperToBeSetUp(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings/domain')->assertOk()->assertSee('hydra-panel update');
        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => '203.0.113.10', 'ssl' => 'off'])->assertStatus(409);
        $this->assertFileDoesNotExist("$this->dir/request");
    }

    public function testChangeLeavesARequestForTheHelper(): void
    {
        $admin = User::factory()->admin()->create();
        $this->installed();

        $this->actingAs($admin)->get('/admin/settings/domain')->assertOk()->assertSee('https://www.example.com');

        $this->actingAs($admin)->postJson('/admin/settings/domain', [
            'domain' => 'HTTPS://Panel.Example.com/',
            'ssl' => 'auto',
            'redirect_old' => 1,
            'force' => 1,
        ])->assertStatus(202)->assertJsonPath('url', 'https://panel.example.com');

        $this->assertSame(
            ['domain' => 'panel.example.com', 'ssl' => 'auto', 'redirect_old' => true, 'drop' => [], 'force' => true],
            $this->request()
        );

        // Only one change at a time.
        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => '203.0.113.10', 'ssl' => 'off'])->assertStatus(409);
    }

    public function testInvalidInputIsRejected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->installed(['old.example.com']);

        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => 'not a domain', 'ssl' => 'auto'])
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'domain');
        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => '203.0.113.10', 'ssl' => 'on'])
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'ssl');
        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => '203.0.113.10', 'ssl' => 'sometimes'])
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'ssl');
        // Only redirects that exist can be removed.
        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => 'www.example.com', 'ssl' => 'on', 'drop' => ['other.example.com']])
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'drop.0');
        // A domain that does not resolve needs "force".
        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => 'hydra-' . bin2hex(random_bytes(6)) . '.invalid', 'ssl' => 'auto'])
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'domain');

        $this->assertFileDoesNotExist("$this->dir/request");
    }

    public function testRedirectsCanBeRemoved(): void
    {
        $admin = User::factory()->admin()->create();
        $this->installed(['old.example.com']);

        $this->actingAs($admin)->postJson('/admin/settings/domain', ['domain' => 'www.example.com', 'ssl' => 'on', 'drop' => ['old.example.com']])
            ->assertStatus(202);

        $this->assertSame(['old.example.com'], $this->request()['drop']);
    }

    public function testNodesArePointedAtThePanel(): void
    {
        $admin = User::factory()->admin()->create();
        $location = Location::factory()->create();
        $ok = Node::factory()->create(['location_id' => $location->id, 'name' => 'A node']);
        $old = Node::factory()->create(['location_id' => $location->id, 'name' => 'B node']);
        $url = rtrim((string) config('app.url'), '/');

        $this->mockNodes($url, [$old->id]);

        $nodes = collect($this->actingAs($admin)->postJson('/admin/settings/domain/nodes')->assertOk()->json('nodes'))->keyBy('id');
        $this->assertTrue($nodes[$ok->id]['ok']);
        $this->assertFalse($nodes[$old->id]['ok']);
        $this->assertSame('outdated', $nodes[$old->id]['code']);

        $status = $this->actingAs($admin)->getJson('/admin/settings/domain/status')->assertOk();
        $this->assertSame($url, $status->json('nodes.url'));
        $this->assertSame('outdated', collect($status->json('nodes.nodes'))->keyBy('id')[$old->id]['code']);
    }

    /**
     * Wings of the given nodes is too old for PUT /api/system/remote.
     */
    private function mockNodes(string $url, array $outdated = []): void
    {
        $this->mock(DaemonRemoteRepository::class, function (MockInterface $mock) use ($url, $outdated) {
            $current = null;
            $mock->allows('setNode')->andReturnUsing(function (Node $node) use ($mock, &$current) {
                $current = $node;

                return $mock;
            });
            $mock->allows('put')->with($url)->andReturnUsing(function () use (&$current, $outdated) {
                if (in_array($current->id, $outdated, true)) {
                    $response = new Response(404, [], '{"error":"not found"}');
                    throw new DaemonConnectionException(new ClientException('not found', new Request('PUT', '/api/system/remote'), $response));
                }
            });
        });
    }

    public function testCommandMovesTheMailSenderAlong(): void
    {
        $this->mockNodes(rtrim((string) config('app.url'), '/'));
        $settings = $this->app->make(SettingsRepositoryInterface::class);
        $settings->set('settings::mail:from:address', 'no-reply@www.example.com');
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST);

        try {
            $this->artisan('hydra:domain:changed', ['--old-domain' => 'www.example.com'])->assertSuccessful();
            if (!DomainService::isIp($domain)) {
                $this->assertSame("no-reply@$domain", $settings->get('settings::mail:from:address', null));
            }

            // Own addresses stay.
            $settings->set('settings::mail:from:address', 'support@example.com');
            $this->artisan('hydra:domain:changed', ['--old-domain' => 'www.example.com'])->assertSuccessful();
            $this->assertSame('support@example.com', $settings->get('settings::mail:from:address', null));
        } finally {
            $settings->forget('settings::mail:from:address');
        }
    }

    public function testChangingNeedsThePermission(): void
    {
        $this->installed();
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Domain ' . uniqid(), 'color' => '#3b9eea', 'permissions' => ['domain.view']]));

        $this->actingAs($user)->get('/admin/settings/domain')->assertOk()->assertDontSee('id="domainForm"', false);
        $this->actingAs($user)->postJson('/admin/settings/domain', ['domain' => '203.0.113.10', 'ssl' => 'off'])->assertForbidden();
        $this->actingAs($user)->postJson('/admin/settings/domain/nodes')->assertForbidden();
        $this->assertFileDoesNotExist("$this->dir/request");

        $other = User::factory()->create();
        $this->actingAs($other)->get('/admin/settings/domain')->assertForbidden();
    }
}
