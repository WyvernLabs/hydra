<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Setting;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\LoaderDesignService;
use Pterodactyl\Services\Hydra\Addons\PrivacyBlurAddon;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: Designer > Loading animation, the size of the sidebar logo and the
 * privacy blur addon.
 */
class LoaderAndPrivacyTest extends HttpTestCase
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

    protected function tearDown(): void
    {
        $this->app->make(BrandingService::class)->reset('loader');
        $this->app->make(BrandingService::class)->setLogoSize(null, null);
        Setting::query()->where('key', 'like', 'settings::hydra:loader:%')->delete();
        Setting::query()->where('key', 'like', 'settings::hydra:addons:%')->delete();
        Setting::query()->where('key', 'like', 'settings::hydra:footer:%')->delete();
        Setting::query()->where('key', 'like', 'settings::hydra:meta:%')->delete();
        config()->set('hydra.footer.texts', null);

        parent::tearDown();
    }

    public function testLoadingAnimationCanBeChosen(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->app->make(LoaderDesignService::class);

        $this->actingAs($admin)->get('/admin/design/loader')->assertOk()
            ->assertSee('hydra-loader--shockwave', false)
            ->assertSee('name="speed"', false);
        $this->assertSame(['style' => 'ring', 'gif' => null, 'speed' => 100], $service->toFrontend());

        $this->actingAs($admin)->patch('/admin/design/loader', ['style' => 'dots', 'speed' => 150])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/design/loader');
        $this->assertSame(['style' => 'dots', 'gif' => null, 'speed' => 150], $service->toFrontend());

        // "Own GIF" needs a GIF.
        $this->actingAs($admin)->patch('/admin/design/loader', ['style' => 'gif', 'speed' => 100])->assertSessionHasErrors('gif');
        $this->assertSame('dots', $service->style());

        $this->actingAs($admin)->patch('/admin/design/loader', [
            'style' => 'gif',
            'speed' => 100,
            'gif' => UploadedFile::fake()->createWithContent('loader.gif', base64_decode(self::GIF)),
        ])->assertSessionHasNoErrors();
        $frontend = $service->toFrontend();
        $this->assertSame('gif', $frontend['style']);
        $this->assertMatchesRegularExpression('#^/branding/loader\?v=[0-9a-f]{8}$#', $frontend['gif']);

        // The GIF is public like the logo.
        auth()->logout();
        $this->get('/branding/loader')->assertOk();

        // Without the GIF the ring is used again.
        $this->app->make(BrandingService::class)->reset('loader');
        $this->assertSame('ring', $service->style());
        $this->get('/branding/loader')->assertNotFound();

        $this->actingAs($admin)->patch('/admin/design/loader', ['style' => 'nope', 'speed' => 100])->assertStatus(422);
        $this->actingAs($admin)->patch('/admin/design/loader', ['style' => 'ring', 'speed' => 1000])->assertStatus(422);
    }

    public function testLogoSizeIsSavedAsCssVariables(): void
    {
        $admin = User::factory()->admin()->create();
        $branding = $this->app->make(BrandingService::class);

        $this->assertStringNotContainsString('--hydra-logo-width', $branding->cssVariables());

        $this->actingAs($admin)->patch('/admin/design/backgrounds', ['logo_width' => 180, 'logo_height' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame(['width' => 180, 'height' => null], $branding->logoSize());
        $css = $branding->cssVariables();
        $this->assertStringContainsString('--hydra-logo-width: 180px;', $css);
        $this->assertStringContainsString('--hydra-logo-max-height: none;', $css);
        $this->assertStringNotContainsString('--hydra-logo-height', $css);

        $this->actingAs($admin)->get('/admin/design/backgrounds')->assertOk()->assertSee('value="180"', false);

        $this->actingAs($admin)->patch('/admin/design/backgrounds', ['logo_width' => 5000])->assertStatus(422);

        // Other forms of the page leave the size alone.
        $this->actingAs($admin)->patch('/admin/design/backgrounds', ['protect_images' => 1])->assertSessionHasNoErrors();
        $this->assertSame(180, $branding->logoSize()['width']);

        $this->actingAs($admin)->patch('/admin/design/backgrounds', ['logo_width' => '', 'logo_height' => ''])->assertSessionHasNoErrors();
        $this->assertSame(['width' => null, 'height' => null], $branding->logoSize());
    }

    public function testFooterTextIsOwnAndCreditStaysInTheAdminArea(): void
    {
        $admin = User::factory()->admin()->create();
        $footer = $this->app->make(\Pterodactyl\Services\Hydra\FooterService::class);

        $this->actingAs($admin)->patch('/admin/design/metadata', [
            'footer' => ['en' => '  Wyvern   Hosting © {year} ', 'de' => '', 'xx' => 'ignored'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['en' => 'Wyvern Hosting © {year}'], $footer->texts());
        $this->assertSame('Wyvern Hosting © ' . date('Y'), $footer->text('en'));
        // Other languages fall back to the default language.
        $this->assertSame('Wyvern Hosting © ' . date('Y'), $footer->text('de'));

        // Pages for users show only the own text, the admin area keeps its footer with the credit.
        $credit = view('partials.hydra.credit')->render();
        $this->assertStringContainsString('Wyvern Hosting © ' . date('Y'), $credit);
        $this->assertStringNotContainsString(BrandingService::CREDIT, $credit);
        $adminFooter = view('partials.hydra.credit', ['admin' => true])->render();
        $this->assertStringNotContainsString('Wyvern Hosting', $adminFooter);
        $this->assertStringContainsString(BrandingService::CREDIT, $adminFooter);

        // Saving other metadata leaves the text alone; empty fields restore the default.
        $this->actingAs($admin)->patch('/admin/design/metadata', ['title' => 'Panel'])->assertSessionHasNoErrors();
        $this->assertSame(['en' => 'Wyvern Hosting © {year}'], $footer->texts());
        $this->actingAs($admin)->patch('/admin/design/metadata', ['footer' => ['en' => '']])->assertSessionHasNoErrors();
        $this->assertNull($footer->text('en'));
    }

    public function testPrivacyBlurIsPrintedOnlyWhereItIsWanted(): void
    {
        $admin = User::factory()->admin()->create();
        $addon = $this->app->make(PrivacyBlurAddon::class);
        $this->assertNull($addon->forPage(false));

        $this->actingAs($admin)->patch('/admin/design/addons/privacy_blur', [
            'enabled' => 1,
            'ips' => 1,
            'emails' => 1,
            'strength' => 7,
        ])->assertSessionHasNoErrors();

        // "admin" was not ticked: client area only.
        $this->assertSame(['ip' => true, 'email' => true, 'strength' => 7], $addon->forPage(false));
        $this->assertNull($addon->forPage(true));

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('/hydra/privacy.js', false);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('/hydra/privacy.js', false);

        $this->actingAs($admin)->patch('/admin/design/addons/privacy_blur', [
            'enabled' => 1,
            'emails' => 1,
            'admin' => 1,
            'strength' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['ip' => false, 'email' => true, 'strength' => 5], $addon->forPage(true));
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('/hydra/privacy.js', false);

        $this->actingAs($admin)->patch('/admin/design/addons/privacy_blur', ['enabled' => 1, 'strength' => 99])->assertStatus(422);
    }
}
