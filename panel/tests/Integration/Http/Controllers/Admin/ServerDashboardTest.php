<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Services\Hydra\Addons\AddonManager;
use Pterodactyl\Services\Hydra\Addons\DashboardAddon;

/**
 * Hydra: server dashboard addon and the banner of the eggs.
 */
class ServerDashboardTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    public function tearDown(): void
    {
        app(AddonManager::class)->setEnabled(app(DashboardAddon::class), false);

        parent::tearDown();
    }

    public function testAddonSettingsReachTheFrontend(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertSame(['enabled' => false, 'landing' => false], app(DashboardAddon::class)->toArray());

        $this->actingAs($admin)->patch('/admin/design/addons/dashboard', ['enabled' => 1, 'landing' => 1])->assertRedirect();
        $this->assertSame(['enabled' => true, 'landing' => true], app(DashboardAddon::class)->toArray());

        $this->actingAs($admin)->patch('/admin/design/addons/dashboard', ['enabled' => 1])->assertRedirect();
        $this->assertSame(['enabled' => true, 'landing' => false], app(DashboardAddon::class)->toArray());

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('"dashboard":{"enabled":true,"landing":false}', false);
    }

    public function testDashboardButtonIsAddedToTheTopOfSavedNavigations(): void
    {
        $navigation = app(ServerNavigation::class);
        $this->assertSame('dashboard', $navigation->get()['categories'][0]['items'][0]['key']);

        // A navigation saved before the dashboard existed.
        config()->set('hydra.navigation.server', json_encode(['categories' => [
            ['id' => 'main', 'name' => [], 'color' => null, 'items' => [['key' => 'console', 'icon' => 'square-terminal', 'eggs' => null]]],
        ]]));

        $items = $navigation->get()['categories'][0]['items'];
        $this->assertSame(['dashboard', 'console'], array_slice(array_column($items, 'key'), 0, 2));

        config()->set('hydra.navigation.server', null);
    }

    public function testEggBannerIsSavedAndSentToTheClient(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $server] = $this->generateTestAccount();
        $egg = $server->egg;

        $this->actingAs($admin)->patch("/admin/nests/egg/$egg->id/banner", [
            'banner_image' => UploadedFile::fake()->image('banner.png', 1600, 400),
        ])->assertRedirect();

        $banner = app(BackgroundService::class)->bannerForEgg($egg->refresh());
        $this->assertMatchesRegularExpression('#^/backgrounds/banner-egg-' . $egg->id . '-[a-z0-9]+\.png$#', $banner);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid")
            ->assertOk()
            ->assertJsonPath('attributes.banner', $banner)
            ->assertJsonPath('attributes.egg_name', $egg->name);

        $this->actingAs($admin, 'web')->patch("/admin/nests/egg/$egg->id/banner", ['reset_banner' => 1])->assertRedirect();
        $this->assertNull($egg->refresh()->hydra_banner);
        $this->assertFileDoesNotExist(BackgroundService::directory() . '/' . basename($banner));

        // An upload is required unless the banner is removed.
        $this->actingAs($admin, 'web')->patchJson("/admin/nests/egg/$egg->id/banner", [])->assertStatus(422);
    }
}
