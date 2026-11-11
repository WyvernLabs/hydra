<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\AdminRole;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\LoginDesignService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: the designer (all design pages) and Designer > Login.
 */
class DesignerTest extends HttpTestCase
{
    // 1×1 GIF.
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    public function tearDown(): void
    {
        $service = $this->app->make(LoginDesignService::class);
        $service->resetImage();
        $service->save([]);

        parent::tearDown();
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Designer ' . uniqid(), 'color' => '#2fb67c', 'permissions' => $permissions]));

        return $user->refresh();
    }

    public function testDesignPagesUseTheDesignerSidebarAndPreview(): void
    {
        $admin = User::factory()->admin()->create();

        $page = $this->actingAs($admin)->get('/admin/design')->assertOk();
        $page->assertSee('hydra-designer-sidebar', false)
            ->assertSee(__('admin/design.designer.back'))
            ->assertSee('id="hydraDesignerPreview"', false)
            ->assertSee('/hydra/designer.js', false)
            ->assertDontSee('nav-tabs-floating', false);

        // Pages without a preview still get the sidebar, but the whole width.
        $this->actingAs($admin)->get('/admin/design/console')->assertOk()
            ->assertSee('hydra-designer-sidebar', false)
            ->assertDontSee('id="hydraDesignerPreview"', false);

        // Other admin pages keep the normal navigation.
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertDontSee('hydra-designer-sidebar', false);

        // Tabs the user may not open are not listed.
        $staff = $this->staff(['design.view']);
        $this->actingAs($staff)->get('/admin/design')->assertOk()
            ->assertSee(route('admin.design.login'), false)
            ->assertDontSee(route('admin.design.addons'), false);
    }

    public function testLoginDesignCanBeSavedAndReachesTheFrontend(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/design/login')->assertOk()->assertSee('name="login_style"', false);

        $this->actingAs($admin)->post('/admin/design/login', [
            '_method' => 'PATCH',
            'login_style' => 'side',
            'login_mirror' => '1',
            'texts' => [
                'title' => ['en' => 'Welcome back', 'de' => ''],
                'subtitle' => ['en' => "Your   servers\nhere"],
                'info' => ['de' => "Demo:\nuser / pass"],
            ],
            'login_image' => UploadedFile::fake()->createWithContent('side.gif', base64_decode(self::GIF)),
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/design/login');

        $service = $this->app->make(LoginDesignService::class);
        $values = $service->values();
        $this->assertSame('side', $values['style']);
        $this->assertTrue($values['mirror']);
        $this->assertSame(['en' => 'Welcome back'], $values['texts']['title']);
        $this->assertSame(['en' => 'Your servers here'], $values['texts']['subtitle']);
        $this->assertSame(['de' => "Demo:\nuser / pass"], $values['texts']['info']);
        $this->assertMatchesRegularExpression('#^/backgrounds/login-side-[a-z0-9]{12}\.gif$#', $values['image']);

        $frontend = $service->toFrontend('en');
        $this->assertSame(['style' => 'side', 'mirror' => true, 'image' => $values['image'], 'title' => 'Welcome back', 'subtitle' => 'Your servers here', 'info' => null], $frontend);
        $this->assertSame("Demo:\nuser / pass", $service->toFrontend('de')['info']);

        // The picture is public, the login page needs it without a session.
        auth()->logout();
        $this->get($values['image'])->assertOk();

        $this->actingAs($admin)->post('/admin/design/login', ['_method' => 'PATCH', 'login_style' => 'nope'])->assertStatus(422);
        $this->actingAs($admin)->post('/admin/design/login', ['_method' => 'PATCH', 'login_style' => 'classic', 'reset_login_image' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['style' => 'classic', 'mirror' => false, 'image' => null], array_slice($service->values(), 0, 3));
    }

    public function testPagesAreGroupedAsColorsImagesAndLogin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/design')->assertOk()
            ->assertSee('name="accent"', false)
            ->assertDontSee('name="logo"', false)
            ->assertDontSee('class="hydra-surface-stage"', false);

        $this->actingAs($admin)->get('/admin/design/backgrounds')->assertOk()
            ->assertSee('name="logo"', false)
            ->assertSee('name="favicon"', false)
            ->assertSee('name="bg_dashboard_image"', false)
            ->assertDontSee('name="bg_login_image"', false);

        $this->actingAs($admin)->get('/admin/design/login')->assertOk()->assertSee('name="bg_login_image"', false);

        // The login background is saved from the login page; the other backgrounds stay untouched.
        config()->set('hydra.backgrounds.dashboard.blur', '7');
        $this->actingAs($admin)->post('/admin/design/login', [
            '_method' => 'PATCH',
            'login_style' => 'classic',
            'bg_login_blur' => 4,
            'bg_login_dim' => 30,
        ])->assertSessionHasNoErrors();
        $this->assertSame('4', (string) config('hydra.backgrounds.login.blur'));
        $this->assertSame('30', (string) config('hydra.backgrounds.login.dim'));
        $this->assertSame('7', (string) config('hydra.backgrounds.dashboard.blur'));

        $this->actingAs($admin)->post('/admin/design/login', ['_method' => 'PATCH', 'login_style' => 'classic', 'reset_background' => 'login'])
            ->assertSessionHasNoErrors();
        $this->assertNull(config('hydra.backgrounds.login.blur'));
    }

    public function testDesignerPreviewMayShowTheLoginPage(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/auth/login')->assertRedirect();
        $this->actingAs($admin)->get('/auth/login?hydra_designer=1')->assertOk();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/auth/login?hydra_designer=1')->assertRedirect();
    }
}
