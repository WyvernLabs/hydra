<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Setting;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Services\Eggs\EggConfigurationService;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: console features — messages that mark a server as running, upload of
 * the log to mclo.gs and the console texts of the admin area.
 */
class ConsoleFeaturesTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
        // Settings are not rolled back, other tests expect the defaults.
        Setting::query()->where('key', 'like', 'settings::hydra:console:%')->delete();
        config()->set('hydra.console', ['offline_message' => null, 'prefix' => null, 'daemon_prefix' => null]);

        parent::tearDown();
    }

    public function testUserSetsMessagesThatMarkTheServerAsRunning(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $service = $this->app->make(EggConfigurationService::class);
        $eggDone = $service->handle($server)['startup']['done'];

        $this->login($user)->getJson($this->link($server, '/startup'))
            ->assertOk()
            ->assertJsonPath('meta.startup_done', null)
            ->assertJsonPath('meta.egg_startup_done', $eggDone);

        $this->login($user)->putJson($this->link($server, '/startup/done'), ['done' => ['Server ready', ' regex:^Done \\(']])
            ->assertOk()
            ->assertJsonPath('startup_done', ['Server ready', 'regex:^Done \\(']);

        // Wings gets them instead of those of the egg.
        $this->assertSame(['Server ready', 'regex:^Done \\('], $service->handle($server->refresh())['startup']['done']);

        $this->login($user)->putJson($this->link($server, '/startup/done'), ['done' => null])->assertOk();
        $this->assertSame($eggDone, $service->handle($server->refresh())['startup']['done']);

        $this->login($user)->putJson($this->link($server, '/startup/done'), ['done' => array_fill(0, 6, 'x')])->assertUnprocessable();
    }

    public function testUserChangesTheStartupCommand(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $egg = $server->egg->startup;

        config()->set('hydra.addons.startup_command.enabled', false);
        $this->login($user)->putJson($this->link($server, '/startup/command'), ['startup' => 'java -jar x.jar'])->assertNotFound();
        $this->login($user)->getJson($this->link($server, '/startup'))->assertJsonPath('meta.startup_editable', false);

        config()->set('hydra.addons.startup_command.enabled', true);
        $this->login($user)->putJson($this->link($server, '/startup/command'), ['startup' => 'java -Xmx{{SERVER_MEMORY}}M -jar x.jar'])
            ->assertOk()
            ->assertJsonPath('raw_startup_command', 'java -Xmx{{SERVER_MEMORY}}M -jar x.jar')
            ->assertJsonPath('startup_command', "java -Xmx{$server->memory}M -jar x.jar");
        $this->assertSame('java -Xmx{{SERVER_MEMORY}}M -jar x.jar', $server->refresh()->startup);

        $this->login($user)->putJson($this->link($server, '/startup/command'), ['startup' => "a\nb"])->assertUnprocessable();

        // Null goes back to the command of the egg.
        $this->login($user)->putJson($this->link($server, '/startup/command'), ['startup' => null])->assertOk();
        $this->assertSame($egg, $server->refresh()->startup);
    }

    public function testSubuserNeedsTheStartupPermission(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_STARTUP_READ]);

        $this->login($user)->putJson($this->link($server, '/startup/done'), ['done' => ['Ready']])->assertForbidden();
    }

    public function testLogIsUploadedToMcLogs(): void
    {
        [$user, $server] = $this->generateTestAccount();
        config()->set('hydra.addons.mclogs', ['enabled' => true, 'path' => 'logs/latest.log']);

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer')->andReturnSelf();
            $mock->expects('getContent')->with('logs/latest.log', \Mockery::any())->andReturn("[12:00:00 INFO]: Done\n");
        });
        Http::fake(['api.mclo.gs/*' => Http::response(['success' => true, 'id' => 'abc', 'url' => 'https://mclo.gs/abc'])]);

        $this->login($user)->postJson($this->link($server, '/files/mclogs'))
            ->assertOk()
            ->assertJsonPath('url', 'https://mclo.gs/abc');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.mclo.gs/1/log' && str_contains($request['content'], 'Done'));
    }

    public function testMcLogsNeedsTheAddonAndThePermission(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);
        config()->set('hydra.addons.mclogs.enabled', false);
        $this->login($user)->postJson($this->link($server, '/files/mclogs'))->assertNotFound();

        config()->set('hydra.addons.mclogs.enabled', true);
        $this->login($user)->postJson($this->link($server, '/files/mclogs'))->assertForbidden();
    }

    public function testAdminSetsConsoleTexts(): void
    {
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
        $admin = User::factory()->admin()->create();

        $this->login($admin)->get('/admin/design/console')->assertOk();
        $this->login($admin)->patch('/admin/design/console', [
            'offline_message' => ['en' => 'Start me with the button above.', 'de' => 'Starte mich oben.', 'xx' => 'ignored'],
            'prefix' => 'panel@wyvern~',
            'daemon_prefix' => '[Wyvern]:',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('panel@wyvern~', config('hydra.console.prefix'));
        $this->assertSame('[Wyvern]:', config('hydra.console.daemon_prefix'));
        $this->assertSame(['en' => 'Start me with the button above.', 'de' => 'Starte mich oben.'], json_decode(config('hydra.console.offline_message'), true));

        $settings = $this->app->make(\Pterodactyl\Services\Hydra\ConsoleSettings::class);
        $this->assertSame('Starte mich oben.', $settings->offlineMessage('de'));
        $this->assertSame('Start me with the button above.', $settings->offlineMessage('fr'));

        // ASCII art keeps its leading spaces and may be long.
        $art = "  __  __\r\n |  \\/  |  &a{name}\r\n" . str_repeat(" |_|  |_|\n", 400) . "\n\n";
        $this->login($admin)->patch('/admin/design/console', ['offline_message' => ['en' => $art]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame(rtrim(str_replace("\r\n", "\n", $art)), $settings->offlineMessage('en'));
        $this->assertStringStartsWith('  __  __', $settings->offlineMessage('en'));
    }

    /**
     * Logs in as the user for the next request with fresh guards.
     */
    private function login(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->actingAs($user);
    }
}
