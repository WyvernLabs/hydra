<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Setting;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: addons in the admin area (Design > Addons).
 */
class AddonTest extends HttpTestCase
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
        // The settings are not rolled back, other tests expect the addons off.
        Setting::query()->where('key', 'like', 'settings::hydra:addons:%')->delete();

        parent::tearDown();
    }

    public function testAdminSeesAddonBoxes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/design/addons')
            ->assertOk()
            ->assertSee(trans('admin/addons.turnstile.name'))
            ->assertSee(trans('admin/addons.registration.name'));

        $this->actingAs($admin)->get('/admin/design/addons/turnstile')->assertOk();
        $this->actingAs($admin)->get('/admin/design/addons/registration')->assertOk();
        $this->actingAs($admin)->get('/admin/design/addons/unknown')->assertNotFound();
    }

    public function testTurnstileNeedsKeysBeforeItCanBeEnabled(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/addons/turnstile/toggle', ['enabled' => 1])
            ->assertRedirect('/admin/design/addons/turnstile');
        $this->assertFalse((bool) config('hydra.addons.turnstile.enabled'));

        $this->actingAs($admin)->patch('/admin/design/addons/turnstile', [
            'enabled' => 1,
            'site_key' => '0x4AAAAAAA',
            'secret_key' => '0x4AAAAAAA-secret',
            'login' => 1,
            'password' => 0,
            'register' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue(config('hydra.addons.turnstile.enabled'));
        $this->assertFalse(config('hydra.addons.turnstile.password'));

        // The secret is stored encrypted and kept when the field stays empty.
        $stored = Setting::query()->where('key', 'settings::hydra:addons:turnstile:secret_key')->value('value');
        $this->assertNotSame('0x4AAAAAAA-secret', $stored);
        $this->assertSame('0x4AAAAAAA-secret', decrypt($stored));

        $this->actingAs($admin)->patch('/admin/design/addons/turnstile', [
            'enabled' => 1,
            'site_key' => '0x4AAAAAAA',
            'secret_key' => '',
            'login' => 1,
            'password' => 1,
            'register' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($stored, Setting::query()->where('key', 'settings::hydra:addons:turnstile:secret_key')->value('value'));

        // The login page now loads the widget for the login form.
        auth()->logout();
        $this->get('/auth/login')->assertOk()->assertSee('"siteKey":"0x4AAAAAAA"', false);
    }

    public function testRegistrationCanBeToggled(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/addons/registration/toggle', ['enabled' => 1])
            ->assertRedirect('/admin/design/addons');
        $this->assertTrue(config('hydra.addons.registration.enabled'));
        $this->assertSame('true', Setting::query()->where('key', 'settings::hydra:addons:registration:enabled')->value('value'));

        $this->actingAs($admin)->patch('/admin/design/addons/registration', [
            'enabled' => 1,
            'code_lifetime' => 500,
        ])->assertStatus(422);
        $this->assertSame(15, (int) config('hydra.addons.registration.code_lifetime'));
    }

    public function testAddonsNeedTheirOwnPermission(): void
    {
        $user = User::factory()->create();
        $role = AdminRole::query()->create(['name' => 'Designer ' . Str::random(6), 'color' => '#b16cf0', 'permissions' => ['design.update']]);
        $user->adminRoles()->attach($role);

        $this->actingAs($user)->get('/admin/design')->assertOk()->assertDontSee('/admin/design/addons');
        $this->actingAs($user)->get('/admin/design/addons')->assertForbidden();

        $role->update(['permissions' => ['design.view', 'addons.view']]);
        $user = $user->fresh();
        $this->actingAs($user)->get('/admin/design/addons')->assertOk();
        $this->actingAs($user)->post('/admin/design/addons/registration/toggle', ['enabled' => 1])->assertForbidden();
    }
}
