<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\PanelLink;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: links and pages in the sidebar of the client area.
 */
class PanelLinkTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    public function testDefaultLinksExist(): void
    {
        $this->assertTrue(PanelLink::query()->where('slug', 'imprint')->where('public', true)->exists());
        $this->assertTrue(PanelLink::query()->where('slug', 'terms')->exists());
        // The Discord link stays hidden until an invite is entered.
        $this->assertFalse(PanelLink::query()->visible()->where('icon', 'discord')->exists());
    }

    public function testAdminCanCreatePageAndLink(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings/links')->assertOk()->assertSee('Imprint');

        $this->actingAs($admin)->post('/admin/settings/links/new', [
            'type' => 'page',
            'title' => ['en' => 'Rules', 'de' => 'Regeln'],
            'icon' => 'book-open',
            'slug' => 'rules',
            'content' => ['en' => '# Rules', 'de' => '# Regeln'],
            'enabled' => 1,
            'public' => 0,
            'sort' => 5,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $page = PanelLink::query()->where('slug', 'rules')->firstOrFail();
        $this->assertSame('Regeln', $page->localizedTitle('de'));
        $this->assertSame('# Rules', $page->localizedContent('en'));

        $this->actingAs($admin)->post('/admin/settings/links/new', [
            'type' => 'url',
            'title' => ['en' => 'Website'],
            'icon' => 'globe-alt',
            'url' => 'javascript:alert(1)',
            'sort' => 6,
        ]);
        $this->assertFalse(PanelLink::query()->where('icon', 'globe-alt')->exists());
    }

    public function testRoleWithoutSettingsPermissionCannotEditLinks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/settings/links')->assertForbidden();
    }

    public function testPublicPageWorksWithoutLogin(): void
    {
        $page = PanelLink::query()->where('slug', 'imprint')->firstOrFail();
        $page->update(['content' => ['en' => "# Imprint\n\nHydra GmbH ||secret||"]]);

        $this->get('/pages/imprint')
            ->assertOk()
            ->assertSee('Hydra GmbH', false)
            ->assertSee('md-spoiler', false);

        $page->update(['public' => false]);
        $this->get('/pages/imprint')->assertRedirect(route('auth.login'));

        $this->get('/pages/does-not-exist')->assertNotFound();
    }

    public function testClientApiRendersPage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/client/pages/terms')
            ->assertOk()
            ->assertJsonPath('attributes.slug', 'terms')
            ->assertJsonPath('attributes.title', 'Terms of Service');

        PanelLink::query()->where('slug', 'terms')->update(['enabled' => false]);
        $this->actingAs($user)->getJson('/api/client/pages/terms')->assertNotFound();
    }
}
