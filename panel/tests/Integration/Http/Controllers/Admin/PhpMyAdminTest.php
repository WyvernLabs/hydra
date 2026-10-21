<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\DatabaseHost;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Services\Hydra\PhpMyAdminService;
use Pterodactyl\Services\Hydra\Addons\AddonManager;
use Pterodactyl\Services\Hydra\Addons\PhpMyAdminAddon;

/**
 * Hydra: phpMyAdmin addon (installation through the helper of the installer
 * and the one-time login).
 */
class PhpMyAdminTest extends HttpTestCase
{
    private string $directory;

    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $this->directory = PhpMyAdminService::directory();
        @mkdir($this->directory, 0775, true);
        @unlink($this->directory . '/request');
        @unlink($this->directory . '/status.json');
        @unlink($this->directory . '/helper');
    }

    public function tearDown(): void
    {
        @unlink($this->directory . '/request');
        @unlink($this->directory . '/status.json');
        @unlink($this->directory . '/helper');
        config()->set('hydra.addons.phpmyadmin.enabled', false);

        parent::tearDown();
    }

    public function testInstallNeedsTheHelper(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/admin/design/addons/phpmyadmin/install')->assertStatus(409);
        $this->assertFileDoesNotExist($this->directory . '/request');

        touch($this->directory . '/helper');
        $this->actingAs($admin)->postJson('/admin/design/addons/phpmyadmin/install')->assertStatus(202);
        $this->assertSame('install', file_get_contents($this->directory . '/request'));

        // Only one run at a time.
        $this->actingAs($admin)->postJson('/admin/design/addons/phpmyadmin/uninstall')->assertStatus(409);

        $this->actingAs($admin)->getJson('/admin/design/addons/phpmyadmin/status')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('busy', true)
            ->assertJsonPath('installed', false);
    }

    public function testAddonCanOnlyBeEnabledWhenInstalled(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/design/addons/phpmyadmin/toggle', ['enabled' => 1])->assertRedirect();
        $this->assertFalse(app(PhpMyAdminAddon::class)->enabled());

        file_put_contents($this->directory . '/status.json', json_encode(['installed' => true, 'version' => '5.2.2', 'state' => 'success']));
        $this->actingAs($admin)->post('/admin/design/addons/phpmyadmin/toggle', ['enabled' => 1])->assertRedirect();
        $this->assertTrue(app(PhpMyAdminAddon::class)->enabled());
        $this->assertTrue(app(PhpMyAdminService::class)->isOffered());

        // Uninstalling switches the addon off right away.
        touch($this->directory . '/helper');
        $this->actingAs($admin)->postJson('/admin/design/addons/phpmyadmin/uninstall')->assertStatus(202);
        $this->assertFalse(app(PhpMyAdminAddon::class)->enabled());

        app(AddonManager::class)->setEnabled(app(PhpMyAdminAddon::class), false);
    }

    public function testLoginTokenHoldsTheCredentialsOfTheDatabase(): void
    {
        $host = DatabaseHost::factory()->create(['host' => '203.0.113.20', 'port' => 3306]);
        $database = Database::factory()->create([
            'server_id' => $this->createServerModel()->id,
            'database_host_id' => $host->id,
            'password' => encrypt('secret-password'),
        ]);

        $url = app(PhpMyAdminService::class)->loginUrl($database);
        $this->assertMatchesRegularExpression('#/phpmyadmin/hydra-signon\.php\?token=([A-Za-z0-9]{64})$#', $url);

        preg_match('/token=([A-Za-z0-9]{64})/', $url, $match);
        $file = $this->directory . '/tokens/' . hash('sha256', $match[1]) . '.json';
        $this->assertFileExists($file);

        $data = json_decode(file_get_contents($file), true);
        $this->assertSame('secret-password', $data['password']);
        $this->assertSame('203.0.113.20', $data['host']);
        $this->assertSame($database->database, $data['database']);
        $this->assertGreaterThan(time(), $data['expires']);

        unlink($file);
    }
}
