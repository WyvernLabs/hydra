<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers;

use Pterodactyl\Models\User;
use Pterodactyl\Models\PanelLink;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: the "Hydra by WyvernLabs®" credit is mandatory and stays in the
 * footer, whatever the panel is called.
 */
class FooterCreditTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        config()->set('app.name', 'Mein Hosting');
    }

    public function testAdminAreaShowsCredit(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Mein Hosting')
            ->assertSee(BrandingService::CREDIT);
    }

    public function testPublicPagesShowCredit(): void
    {
        // Other tests may have hidden the imprint, nothing is rolled back between tests.
        PanelLink::query()->where('slug', 'imprint')->update(['public' => true, 'enabled' => true]);

        $this->get('/pages/imprint')->assertOk()->assertSee(BrandingService::CREDIT);
    }
}
