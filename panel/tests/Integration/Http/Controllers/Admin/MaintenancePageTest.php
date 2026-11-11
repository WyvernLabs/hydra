<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Services\Hydra\MaintenancePageService;

/**
 * Hydra: the 503 page shown during updates (Design > Maintenance page).
 */
class MaintenancePageTest extends HttpTestCase
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
        $this->app->make(MaintenancePageService::class)->resetImage();
        $this->app->make(MaintenancePageService::class)->save([]);
        if ($this->app->maintenanceMode()->active()) {
            $this->app->maintenanceMode()->deactivate();
        }

        parent::tearDown();
    }

    private function page(): MaintenancePageService
    {
        return $this->app->make(MaintenancePageService::class);
    }

    public function testDefaultPageIsSelfContained(): void
    {
        $html = $this->page()->render();

        $this->assertStringContainsString(trans('maintenance.title', [], 'de'), $html);
        $this->assertStringContainsString('Under maintenance', $html);
        $this->assertStringContainsString('--hydra-surface-800', $html);
        $this->assertStringContainsString('hydra-503-float', $html);
        $this->assertStringContainsString('Hydra by WyvernLabs', $html);
        $this->assertStringContainsString('/hydra/logo.svg', $html);
        // Nothing that needs PHP while the panel is down.
        $this->assertStringNotContainsString('/branding/', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        $this->assertStringContainsString('setInterval', $html);
    }

    public function testSettingsCanBeSavedAndAreShown(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/design/maintenance')->assertOk()->assertSee(trans('maintenance.title', [], 'de'));

        $this->actingAs($admin)->post('/admin/design/maintenance', [
            '_method' => 'PATCH',
            'title' => ['de' => 'Kurz weg', 'en' => ''],
            'description' => ['en' => "Back <soon>\nPromise"],
            'bg_image' => UploadedFile::fake()->createWithContent('bg.gif', base64_decode(self::GIF)),
            'bg_blur' => '5',
            'bg_dim' => '40',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/design/maintenance');

        $values = $this->page()->values();
        $this->assertSame(['de' => 'Kurz weg'], $values['title']);
        $this->assertSame(['en' => "Back <soon>\nPromise"], $values['description']);
        $this->assertFalse($values['logo']);
        $this->assertSame(5, $values['blur']);
        $this->assertSame(40, $values['dim']);
        $this->assertMatchesRegularExpression('#^/hydra-maintenance/background-[a-z0-9]{16}\.gif$#', $values['image']);
        $this->assertFileExists(public_path(ltrim($values['image'], '/')));

        $html = $this->page()->render();
        $this->assertStringContainsString('Kurz weg', $html);
        $this->assertStringContainsString(trim(json_encode("Back <soon>\nPromise", JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), '"'), $html);
        $this->assertStringNotContainsString('Back <soon>', $html);
        $this->assertStringContainsString($values['image'], $html);
        $this->assertStringContainsString('--blur: 5px', $html);
        $this->assertStringNotContainsString('hydra-503-logo', preg_replace('/<style>.*?<\/style>/s', '', $html));

        // Resetting the background removes the file.
        $this->actingAs($admin)->post('/admin/design/maintenance', ['_method' => 'PATCH', 'logo' => '1', 'reset_background' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->page()->values()['image']);
        $this->assertFileDoesNotExist(public_path(ltrim($values['image'], '/')));
        $this->assertTrue($this->page()->values()['logo']);
    }

    public function testInvalidValuesAreRejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/maintenance', [
            '_method' => 'PATCH',
            'title' => ['de' => str_repeat('a', MaintenancePageService::MAX_TITLE + 1)],
            'bg_image' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ])->assertStatus(422);

        $this->assertSame([], $this->page()->values()['title']);
        $this->assertFalse(File::isDirectory(MaintenancePageService::directory()) && count(File::files(MaintenancePageService::directory())) > 0);
    }

    public function testPreviewShowsTheLanguageAndDoesNotReload(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/design/maintenance/preview?lang=en')->assertOk();
        $this->assertStringContainsString('Under maintenance', $response->getContent());
        $this->assertStringNotContainsString('setInterval', $response->getContent());

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/design/maintenance/preview')->assertForbidden();
    }

    public function testMaintenanceModeShowsThePageAndLetsTheUpdateStatusThrough(): void
    {
        $admin = User::factory()->admin()->create();

        $this->artisan('hydra:maintenance:render')->expectsOutput('The panel is not in maintenance mode.')->assertSuccessful();

        $this->app->maintenanceMode()->activate(['template' => 'old', 'except' => [], 'status' => 503]);
        $this->artisan('hydra:maintenance:render')->expectsOutput('Maintenance page rendered.')->assertSuccessful();

        $data = $this->app->maintenanceMode()->data();
        $this->assertStringContainsString('hydra-503-box', $data['template']);
        $this->assertContains('admin/settings/updates/status', $data['except']);
        $this->assertSame(30, $data['retry']);

        $this->get('/auth/login')->assertStatus(503)->assertSee('hydra-503-box', false);
        $this->actingAs($admin)->getJson('/admin/settings/updates/status')->assertOk()->assertJsonStructure(['busy', 'progress']);
    }
}
