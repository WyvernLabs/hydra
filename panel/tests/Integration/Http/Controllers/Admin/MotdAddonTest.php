<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Pterodactyl\Jobs\Hydra\SyncMotdJob;
use Pterodactyl\Services\Hydra\Addons\MotdAddon;
use Pterodactyl\Services\Hydra\Motd\MotdService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Tests\Traits\Integration\CreatesTestModels;

/**
 * Hydra: Minecraft MOTD (addon "motd").
 */
class MotdAddonTest extends HttpTestCase
{
    use CreatesTestModels;

    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Setting::query()->where('key', 'like', 'settings::hydra:addons:%')->delete();
        @unlink(storage_path('app/' . MotdAddon::FAVICON));

        parent::tearDown();
    }

    public function testAdminSavesTextsAndPicture(): void
    {
        $admin = User::factory()->admin()->create();
        $egg = Egg::query()->firstOrFail();
        // A node to send the settings to.
        $this->generateTestAccount();

        $this->actingAs($admin)->get('/admin/design/addons/motd')->assertOk()->assertSee('&amp;cOffline', false);

        // Without eggs it cannot be switched on.
        $this->actingAs($admin)->patch('/admin/design/addons/motd', ['enabled' => 1, 'states' => ['offline' => ['line1' => 'x']]])
            ->assertRedirect('/admin/design/addons/motd');
        $this->assertFalse((bool) config('hydra.addons.motd.enabled'));

        $this->actingAs($admin)->patch('/admin/design/addons/motd', [
            'enabled' => 1,
            'eggs' => [$egg->id],
            'states' => ['offline' => ['line1' => '&aHello {name}', 'line2' => 'at {panel}', 'label' => '&cZu']],
            'favicon_upload' => UploadedFile::fake()->image('logo.png', 300, 200),
        ])->assertRedirect('/admin/design/addons/motd');

        $addon = app(MotdAddon::class);
        $this->assertTrue($addon->enabled());
        $this->assertSame([$egg->id], $addon->eggIds());
        $this->assertSame('&aHello {name}', $addon->states()['offline']['line1']);
        // Not set: the default texts.
        $this->assertSame(trans('admin/addons.motd.defaults.starting.label'), $addon->states()['starting']['label']);

        $size = getimagesizefromstring(base64_decode($addon->favicon()));
        $this->assertSame([64, 64], [$size[0], $size[1]]);

        Queue::assertPushed(SyncMotdJob::class);
    }

    public function testPayloadListsTheMinecraftServersOfTheNode(): void
    {
        [, $server] = $this->generateTestAccount();
        [, $other] = $this->generateTestAccount();
        $other->forceFill(['egg_id' => Egg::query()->where('id', '!=', $server->egg_id)->value('id')])->saveQuietly();

        config()->set('hydra.addons.motd.enabled', true);
        config()->set('hydra.addons.motd.eggs', [$server->egg_id]);
        config()->set('app.url', 'https://panel.example.com');
        config()->set('hydra.addons.motd.states', ['offline' => ['line1' => 'Start at {panel}', 'line2' => '', 'label' => '']]);

        $payload = app(MotdService::class)->payload($server->node);

        $this->assertTrue($payload['enabled']);
        $this->assertContains($server->uuid, $payload['servers']);
        $this->assertNotContains($other->uuid, $payload['servers']);
        $this->assertSame($server->name, ((array) $payload['names'])[$server->uuid]);
        $this->assertSame('Start at panel.example.com', $payload['states']['offline']['line1']);

        config()->set('hydra.addons.motd.enabled', false);
        $this->assertFalse(app(MotdService::class)->payload($server->node)['enabled']);
        $this->assertSame([], app(MotdService::class)->payload($server->node)['servers']);
    }

    public function testRenamingAMinecraftServerSyncsItsNode(): void
    {
        [, $server] = $this->generateTestAccount();
        config()->set('hydra.addons.motd.enabled', true);
        config()->set('hydra.addons.motd.eggs', [$server->egg_id]);

        $server->update(['name' => 'Renamed']);
        Queue::assertPushed(SyncMotdJob::class, fn (SyncMotdJob $job) => $job->node === $server->node_id);

        Queue::fake();
        $server->update(['description' => 'only the description']);
        Queue::assertNothingPushed();
    }
}
