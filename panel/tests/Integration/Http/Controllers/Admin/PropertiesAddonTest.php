<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Setting;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Services\Hydra\Addons\PropertiesAddon;

/**
 * Hydra: the properties addon keeps its eggs on its navigation button.
 */
class PropertiesAddonTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    protected function tearDown(): void
    {
        Setting::query()->where('key', 'like', 'settings::hydra:addons:properties:%')->delete();
        app(ServerNavigation::class)->reset();

        parent::tearDown();
    }

    public function testEggsAreStoredOnTheNavigationButton(): void
    {
        $admin = User::factory()->admin()->create();
        $egg = Egg::query()->firstOrFail();
        $navigation = app(ServerNavigation::class);

        // The button exists for every egg until the addon says otherwise.
        $this->assertNull($navigation->buttonEggs(PropertiesAddon::BUTTON));

        $this->actingAs($admin)->get('/admin/design/addons/properties')->assertOk();
        $this->actingAs($admin)->patch('/admin/design/addons/properties', [
            'enabled' => 1,
            'path' => 'server.properties',
            'eggs' => [$egg->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame([$egg->id], $navigation->buttonEggs(PropertiesAddon::BUTTON));
        $this->assertSame([$egg->id], app(PropertiesAddon::class)->eggs());
        $this->assertTrue((bool) config('hydra.addons.properties.enabled'));

        // Nothing selected shows it for every egg again.
        $this->actingAs($admin)->patch('/admin/design/addons/properties', [
            'enabled' => 1,
            'path' => 'server.properties',
        ])->assertSessionHasNoErrors();
        $this->assertNull($navigation->buttonEggs(PropertiesAddon::BUTTON));

        // Only .properties files.
        $this->actingAs($admin)->patch('/admin/design/addons/properties', [
            'enabled' => 1,
            'path' => '../.env',
        ])->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'path');
    }
}
