<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Base;

use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\PwaService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: the panel as installable app (addon "pwa").
 */
class PwaTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.pwa', [
            'enabled' => true,
            'name' => 'Hydra Test',
            'short_name' => null,
            'theme_color' => '#181818',
            'background_color' => '#121212',
            'tabs' => true,
        ]);
        app(PwaService::class)->removeIcon();
    }

    public function testManifestIsPublicWhileTheAddonIsOn(): void
    {
        $this->getJson('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'Hydra Test')
            ->assertJsonPath('short_name', 'Hydra Test')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/')
            ->assertJsonCount(4, 'icons');

        config()->set('hydra.addons.pwa.enabled', false);
        $this->getJson('/manifest.webmanifest')->assertNotFound();
        $this->get('/pwa/icon-192.png')->assertNotFound();
    }

    public function testIconsAreDrawnInBothSizes(): void
    {
        foreach (PwaService::SIZES as $size) {
            foreach (['icon', 'maskable'] as $type) {
                $response = $this->get("/pwa/$type-$size.png")->assertOk()->assertHeader('Content-Type', 'image/png');
                [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname());
                $this->assertSame([$size, $size], [$width, $height]);
            }
        }

        $this->get('/pwa/icon-100.png')->assertNotFound();
    }

    public function testUploadedIconReplacesTheDrawnOne(): void
    {
        $before = app(PwaService::class)->version();
        app(PwaService::class)->storeIcon(UploadedFile::fake()->image('icon.png', 600, 400));

        $this->assertTrue(app(PwaService::class)->hasIcon());
        $this->assertNotSame($before, app(PwaService::class)->version());
        $response = $this->get('/pwa/icon-512.png')->assertOk();
        $this->assertSame(512, getimagesize($response->baseResponse->getFile()->getPathname())[0]);

        app(PwaService::class)->removeIcon();
        $this->assertFalse(app(PwaService::class)->hasIcon());
    }

    public function testPagesMayBeFramedByThePanelOnly(): void
    {
        $this->get('/auth/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
