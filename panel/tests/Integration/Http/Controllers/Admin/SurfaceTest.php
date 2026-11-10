<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Services\Hydra\SurfaceService;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: colors and transparency of the boxes (Design > Colors & Logo).
 */
class SurfaceTest extends HttpTestCase
{
    private function save(User $user, array $surface)
    {
        return $this->actingAs($user)->post('/admin/design', [
            '_method' => 'PATCH',
            'accent' => '#74c236',
            'surface' => $surface,
        ]);
    }

    public function testDefaultsKeepTheBuiltInGrayScale(): void
    {
        $css = $this->app->make(SurfaceService::class)->cssVariables();

        $this->assertStringContainsString('--hydra-surface-900: 20 20 20;', $css);
        $this->assertStringContainsString('--hydra-surface-800: 31 31 31;', $css);
        $this->assertStringContainsString('--hydra-surface-700: 38 38 38;', $css);
        $this->assertStringContainsString('--hydra-surface-650: 45 45 45;', $css);
        $this->assertStringContainsString('--hydra-surface-600: 58 58 58;', $css);
        $this->assertStringContainsString('--hydra-line-700: 38 38 38;', $css);
        $this->assertStringContainsString('--hydra-surface-alpha: 1;', $css);
        $this->assertStringContainsString('--hydra-backdrop: none;', $css);
    }

    public function testGlassStyleCanBeSavedAndIsPrintedOnEveryPage(): void
    {
        $admin = User::factory()->admin()->create();

        $this->save($admin, SurfaceService::PRESETS['glass'])->assertSessionHasNoErrors()->assertRedirect();

        $service = $this->app->make(SurfaceService::class);
        $this->assertSame(SurfaceService::PRESETS['glass'], $service->values());

        $css = $service->cssVariables();
        $this->assertStringContainsString('--hydra-surface-800: 20 20 20;', $css);
        $this->assertStringContainsString('--hydra-surface-alpha: 0.6;', $css);
        $this->assertStringContainsString('--hydra-float-alpha: 0.88;', $css);
        $this->assertStringContainsString('--hydra-line-alpha: 0.12;', $css);
        $this->assertStringContainsString('--hydra-backdrop: blur(16px) saturate(140%);', $css);

        $head = view('partials.hydra.head', ['branding' => $this->app->make(BrandingService::class)])->render();
        $this->assertStringContainsString('--hydra-surface-alpha: 0.6;', $head);

        // Back to the defaults removes the settings again.
        $this->save($admin, SurfaceService::DEFAULTS)->assertSessionHasNoErrors();
        $this->assertSame(SurfaceService::DEFAULTS, $service->values());
        $this->assertDatabaseMissing('settings', ['key' => 'settings::hydra:surfaces:box']);
    }

    public function testInvalidValuesAreRejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->save($admin, ['box' => 'red', 'opacity' => 0, 'blur' => 500])->assertStatus(422);
        foreach (['surface.box', 'surface.opacity', 'surface.blur'] as $field) {
            $response->assertJsonFragment(['source_field' => $field]);
        }

        $this->assertSame(SurfaceService::DEFAULTS, $this->app->make(SurfaceService::class)->values());
    }

    public function testSavingWithoutSurfaceFieldsKeepsThem(): void
    {
        $admin = User::factory()->admin()->create();
        $this->save($admin, ['opacity' => 50])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/admin/design', ['_method' => 'PATCH', 'accent' => '#3b9eea'])
            ->assertSessionHasNoErrors();

        $this->assertSame(50, $this->app->make(SurfaceService::class)->values()['opacity']);
    }

    public function testNormalizeKeepsValuesInRange(): void
    {
        $this->assertSame(
            ['box' => '#abcdef', 'inner' => '#262626', 'border' => '#2e2e2e', 'opacity' => SurfaceService::MIN_OPACITY, 'border_opacity' => 100, 'blur' => SurfaceService::MAX_BLUR],
            SurfaceService::normalize(['box' => '#ABCDEF', 'inner' => 'nope', 'opacity' => '-5', 'border_opacity' => 400, 'blur' => 99])
        );
    }
}
