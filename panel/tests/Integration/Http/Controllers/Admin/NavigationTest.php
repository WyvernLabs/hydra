<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Illuminate\Support\Str;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Setting;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Services\Hydra\Icons;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: navigation of the server pages (Design > Navigation) and Lucide icons.
 */
class NavigationTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    protected function tearDown(): void
    {
        // Nothing is rolled back between tests, other tests expect the default navigation.
        Setting::query()->where('key', ServerNavigation::SETTING)->delete();

        parent::tearDown();
    }

    public function testAdminCanSaveCategoriesIconsColorsAndEggs(): void
    {
        $admin = User::factory()->admin()->create();
        $egg = Egg::query()->firstOrFail();

        $this->actingAs($admin)->get('/admin/design/navigation')->assertOk()->assertSee('hydraNavEditor', false);

        $navigation = ServerNavigation::defaults();
        $files = array_splice($navigation['categories'][0]['items'], 1, 1)[0];
        $files['icon'] = 'folder-open';
        $files['eggs'] = [$egg->id];
        $navigation['categories'][] = [
            'id' => 'data',
            'name' => ['en' => 'Data', 'de' => 'Daten', 'xx' => 'ignored'],
            'color' => '#E5534B',
            'items' => [$files],
        ];

        $this->actingAs($admin)->patch('/admin/design/navigation', ['navigation' => json_encode($navigation)])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/design/navigation');

        $saved = app(ServerNavigation::class)->get()['categories'];
        $this->assertCount(2, $saved);
        $this->assertEquals(['en' => 'Data', 'de' => 'Daten'], $saved[1]['name']);
        $this->assertSame('#e5534b', $saved[1]['color']);
        $this->assertSame(['key' => 'files', 'icon' => 'folder-open', 'eggs' => [$egg->id]], $saved[1]['items'][0]);
        $this->assertNotContains('files', array_column($saved[0]['items'], 'key'));

        // The client area gets the navigation and the drawing data of its icons.
        auth()->logout();
        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertSee('"serverNavigation":', false)
            ->assertSee('"folder-open":[[', false);
    }

    public function testInvalidNavigationsAreRejected(): void
    {
        $admin = User::factory()->admin()->create();

        $unknownIcon = ServerNavigation::defaults();
        $unknownIcon['categories'][0]['items'][0]['icon'] = 'not-an-icon';

        $duplicate = ServerNavigation::defaults();
        $duplicate['categories'][0]['items'][] = $duplicate['categories'][0]['items'][0];

        $unknownEgg = ServerNavigation::defaults();
        $unknownEgg['categories'][0]['items'][0]['eggs'] = [999999];

        $badColor = ServerNavigation::defaults();
        $badColor['categories'][0]['color'] = 'red';

        foreach ([['categories' => []], $unknownIcon, $duplicate, $unknownEgg, $badColor] as $navigation) {
            $this->actingAs($admin)->patch('/admin/design/navigation', ['navigation' => json_encode($navigation)])
                ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'navigation');
        }

        $this->assertNull(Setting::query()->where('key', ServerNavigation::SETTING)->value('value'));
    }

    public function testMissingButtonsAreAddedAndResetRestoresDefault(): void
    {
        $admin = User::factory()->admin()->create();

        // Buttons left out (e.g. added by an update) end up in the first category.
        $this->actingAs($admin)->patch('/admin/design/navigation', ['navigation' => json_encode([
            'categories' => [['id' => 'only', 'name' => [], 'color' => null, 'items' => [['key' => 'files', 'icon' => 'folder', 'eggs' => null]]]],
        ])])->assertSessionHasNoErrors();

        $items = app(ServerNavigation::class)->get()['categories'][0]['items'];
        $this->assertSame('files', $items[0]['key']);
        $this->assertEqualsCanonicalizing(array_keys(ServerNavigation::BUTTONS), array_column($items, 'key'));

        $this->actingAs($admin)->patch('/admin/design/navigation', ['reset' => 1])->assertRedirect();
        $this->assertSame(ServerNavigation::defaults(), app(ServerNavigation::class)->get());
    }

    public function testNavigationNeedsDesignPermission(): void
    {
        $user = User::factory()->create();
        $role = AdminRole::query()->create(['name' => 'Viewer ' . Str::random(6), 'color' => '#3b9eea', 'permissions' => ['design.view']]);
        $user->adminRoles()->attach($role);

        $this->actingAs($user)->get('/admin/design/navigation')->assertOk();
        $this->actingAs($user)->patch('/admin/design/navigation', ['reset' => 1])->assertForbidden();
    }

    public function testIconsRenderAsLucideSvg(): void
    {
        $this->assertTrue(Icons::exists('square-terminal'));
        $this->assertTrue(Icons::exists('discord'));
        $this->assertFalse(Icons::exists('document-text'));

        $svg = (string) Icons::svg('house', 'my-class');
        $this->assertStringContainsString('class="my-class"', $svg);
        $this->assertStringContainsString('stroke-width="2"', $svg);
        $this->assertStringContainsString('<path d="M15 21v-8', $svg);

        // Unknown names fall back to a circle instead of breaking the page.
        $this->assertStringContainsString('<circle', (string) Icons::svg('not-an-icon'));
    }
}
