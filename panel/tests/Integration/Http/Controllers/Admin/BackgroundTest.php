<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: backgrounds of the login page, the dashboard and the eggs.
 */
class BackgroundTest extends HttpTestCase
{
    // 1×1 GIF.
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private function appearance(array $data = []): array
    {
        return array_merge(['_method' => 'PATCH'], $data);
    }

    public function testLoginAndDashboardBackgroundsCanBeSetAndReset(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->app->make(BackgroundService::class);

        $this->actingAs($admin)->post('/admin/design/backgrounds', $this->appearance([
            'bg_login_image' => UploadedFile::fake()->createWithContent('login.gif', base64_decode(self::GIF)),
            'bg_login_blur' => 8,
            'bg_login_dim' => 40,
            'bg_dashboard_blur' => 0,
            'bg_dashboard_dim' => 25,
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $login = $service->forSlot('login');
        $this->assertStringStartsWith('/backgrounds/login-', $login['image']);
        $this->assertSame(8, $login['blur']);
        $this->assertSame(40, $login['dim']);
        $this->assertSame(['image' => null, 'blur' => 0, 'dim' => 25], $service->forSlot('dashboard'));

        // Served without a login, since the login page needs it.
        auth()->logout();
        $this->get($login['image'])->assertOk()->assertHeader('Content-Type', 'image/gif');

        $path = BackgroundService::path(basename($login['image']));
        $this->actingAs($admin)->post('/admin/design/backgrounds', $this->appearance(['reset_background' => 'login']))
            ->assertRedirect();

        $this->assertSame(['image' => null, 'blur' => 0, 'dim' => 0], $service->forSlot('login'));
        $this->assertFileDoesNotExist($path);
    }

    public function testValuesAreLimited(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/backgrounds', $this->appearance([
            'bg_login_blur' => 500,
            'bg_login_image' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ]));

        $this->assertSame(['image' => null, 'blur' => 0, 'dim' => 0], $this->app->make(BackgroundService::class)->forSlot('login'));
    }

    public function testEggBackground(): void
    {
        $admin = User::factory()->admin()->create();
        /** @var Egg $egg */
        $egg = Egg::query()->firstOrFail();
        $service = $this->app->make(BackgroundService::class);
        $this->assertNull($service->forEgg($egg));

        $this->actingAs($admin)->patch("/admin/nests/egg/$egg->id/background", [
            'bg_egg_image' => UploadedFile::fake()->createWithContent('egg.gif', base64_decode(self::GIF)),
            'bg_egg_blur' => 4,
            'bg_egg_dim' => 60,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $background = $service->forEgg($egg->refresh());
        $this->assertStringStartsWith("/backgrounds/egg-$egg->id-", $background['image']);
        $this->assertSame(60, $background['dim']);

        // Egg exports stay compatible with Pterodactyl.
        $this->actingAs($admin)->get("/admin/nests/egg/$egg->id/export")->assertOk()->assertDontSee('hydra_background');

        $this->actingAs($admin)->patch("/admin/nests/egg/$egg->id/background", ['reset_background' => 1])->assertRedirect();
        $this->assertNull($service->forEgg($egg->refresh()));
    }
}
