<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\MetadataService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: meta tags (Design > Metadata) and the protection of the logo.
 */
class MetadataTest extends HttpTestCase
{
    // 1×1 GIF.
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private function renderHead(string $path = '/'): string
    {
        $this->app->instance('request', \Illuminate\Http\Request::create($path));

        return view('partials.hydra.head', ['branding' => $this->app->make(BrandingService::class)])->render();
    }

    public function testDefaultsKeepThePanelOutOfSearchEngines(): void
    {
        $head = $this->renderHead();

        $this->assertStringContainsString('<meta name="robots" content="noindex">', $head);
        $this->assertStringContainsString('<meta property="og:title" content="' . e(config('app.name')) . '">', $head);
        $this->assertStringNotContainsString('og:image', $head);
        $this->assertStringNotContainsString('name="description"', $head);
    }

    public function testMetadataCanBeSavedAndIsPrinted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/metadata', [
            '_method' => 'PATCH',
            'title' => 'Wyvern Hosting',
            'description' => "Gameserver <b>schnell</b>\n und günstig",
            'keywords' => 'minecraft, hosting',
            'use_color' => '1',
            'color' => '#FF0080',
            'large_image' => '1',
            'indexable' => '1',
            'image' => UploadedFile::fake()->createWithContent('preview.gif', base64_decode(self::GIF)),
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/design/metadata');

        $values = $this->app->make(MetadataService::class)->values();
        $this->assertSame('Gameserver <b>schnell</b> und günstig', $values['description']);
        $this->assertSame('#ff0080', $values['color']);
        $this->assertTrue($values['indexable']);

        $head = $this->renderHead();
        $this->assertStringNotContainsString('name="robots"', $head);
        $this->assertStringContainsString('<meta property="og:title" content="Wyvern Hosting">', $head);
        $this->assertStringContainsString('<meta name="description" content="Gameserver &lt;b&gt;schnell&lt;/b&gt; und günstig">', $head);
        $this->assertStringContainsString('<meta name="keywords" content="minecraft, hosting">', $head);
        $this->assertStringContainsString('<meta name="theme-color" content="#ff0080">', $head);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $head);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="http[^"]+/branding/meta\?v=[0-9a-f]{8}">#', $head);

        // The image is public, link previews are fetched without a login.
        auth()->logout();
        $this->get('/branding/meta')->assertOk()->assertHeader('Content-Type', 'image/gif');

        // Clearing everything goes back to the defaults.
        $this->actingAs($admin)->post('/admin/design/metadata', ['_method' => 'PATCH', 'reset_image' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['title' => '', 'description' => '', 'keywords' => '', 'color' => null, 'large_image' => false, 'indexable' => false],
            $this->app->make(MetadataService::class)->values()
        );
        $this->get('/branding/meta')->assertNotFound();
    }

    public function testInvalidValuesAreRejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/metadata', [
            '_method' => 'PATCH',
            'description' => str_repeat('a', MetadataService::MAX_DESCRIPTION + 1),
            'color' => 'pink',
            'image' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ])->assertStatus(422)
            ->assertJsonFragment(['source_field' => 'description'])
            ->assertJsonFragment(['source_field' => 'color'])
            ->assertJsonFragment(['source_field' => 'image']);
    }

    public function testAdminAreaGetsNoLinkPreview(): void
    {
        $this->assertStringNotContainsString('og:title', $this->renderHead('/admin/users'));
    }

    public function testLogoProtectionCanBeSwitchedOff(): void
    {
        $admin = User::factory()->admin()->create();
        $branding = $this->app->make(BrandingService::class);

        $this->assertTrue($branding->protectsImages());
        $this->assertSame('hydra-protected', $branding->protectedClass());
        $this->assertTrue($branding->toArray()['protect']);

        $this->actingAs($admin)->post('/admin/design', ['_method' => 'PATCH', 'accent' => '#74c236', 'protect_images' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($branding->protectsImages());
        $this->assertSame('', $branding->protectedClass());
        $this->assertFalse($branding->toArray()['protect']);

        $this->actingAs($admin)->post('/admin/design', ['_method' => 'PATCH', 'accent' => '#74c236', 'protect_images' => '1']);
        $this->assertTrue($branding->protectsImages());
        $this->assertDatabaseMissing('settings', ['key' => 'settings::hydra:branding:protect']);
    }
}
