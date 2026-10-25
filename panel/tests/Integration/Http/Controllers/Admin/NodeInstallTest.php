<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: installing Hydra Wings on a node from the panel.
 */
class NodeInstallTest extends HttpTestCase
{
    private string $installer;

    private ?string $original = null;

    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $this->installer = storage_path('app/hydra/installer/wings-install.sh');
        if (is_file($this->installer)) {
            $this->original = file_get_contents($this->installer);
        }
        @mkdir(dirname($this->installer), 0755, true);
        file_put_contents($this->installer, "#!/usr/bin/env bash\nset -Eeuo pipefail\nORIGINAL_ARGS=(\"\$@\")\n");
    }

    public function tearDown(): void
    {
        is_null($this->original) ? @unlink($this->installer) : file_put_contents($this->installer, $this->original);

        parent::tearDown();
    }

    private function node(array $attributes = []): Node
    {
        return Node::factory()->create($attributes + ['location_id' => Location::factory()->create()->id, 'fqdn' => 'node.example.com', 'scheme' => 'https']);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => $permissions]));

        return $user;
    }

    public function testInstallNeedsItsPermission(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view']);

        $this->actingAs($user)->get("/admin/nodes/view/$node->id/install")->assertForbidden();
        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/install/command")->assertForbidden();
        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/install/ssh", [])->assertForbidden();
        $this->actingAs($user)->get("/admin/nodes/view/$node->id/servers")
            ->assertOk()
            ->assertDontSee("/admin/nodes/view/$node->id/install");
    }

    public function testInstallCommandHandsOutThePresetInstallerOnce(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.install']);

        $this->actingAs($user)->get("/admin/nodes/view/$node->id/install")
            ->assertOk()
            ->assertSee(__('admin/nodes.install.ssh.title'));

        $command = $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/install/command")
            ->assertOk()
            ->json('command');
        $this->assertMatchesRegularExpression('#^bash <\(curl -fsSL .+/install/node/[A-Za-z0-9]{40}\)$#', $command);
        preg_match('#/install/node/([A-Za-z0-9]{40})#', $command, $match);

        $script = $this->get("/install/node/{$match[1]}")->assertOk()->getContent();
        $lines = explode("\n", $script);
        $this->assertSame('#!/usr/bin/env bash', $lines[0]);
        $this->assertStringStartsWith("set -- '--yes' '--fqdn' 'node.example.com' '--email' '{$user->email}'", $lines[2]);
        $this->assertStringContainsString("'--node' '{$node->id}' '--ssl'", $lines[2]);
        $this->assertSame('set -Eeuo pipefail', $lines[3]);

        // The token is the deployment key of the admin.
        $key = ApiKey::query()->where('user_id', $user->id)->where('r_nodes', 3)->firstOrFail();
        $this->assertStringContainsString("'--token' '{$key->identifier}", $lines[2]);

        // Used up.
        $this->get("/install/node/{$match[1]}")->assertOk()->assertSee('exit 1', false)->assertDontSee('--token', false);
    }

    public function testNodeWithoutCertificate(): void
    {
        $node = $this->node(['fqdn' => '203.0.113.20', 'scheme' => 'https']);
        $user = $this->userWith(['nodes.view', 'nodes.install']);

        $command = $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/install/command")->json('command');
        preg_match('#/install/node/([A-Za-z0-9]{40})#', $command, $match);

        $this->assertStringContainsString("'--no-ssl'", $this->get("/install/node/{$match[1]}")->getContent());
    }

    public function testSshNeedsCredentialsAndShowsNoStateAtFirst(): void
    {
        $node = $this->node();
        $user = $this->userWith(['nodes.view', 'nodes.install']);

        $this->actingAs($user)->postJson("/admin/nodes/view/$node->id/install/ssh", [
            'host' => 'node.example.com', 'port' => 22, 'username' => 'root',
        ])->assertStatus(422)->assertJsonPath('message', __('admin/nodes.install.ssh.no_credentials'));

        $this->actingAs($user)->getJson("/admin/nodes/view/$node->id/install/status")
            ->assertOk()
            ->assertJsonPath('state', 'none');
    }

    public function testNewNodesOpenTheInstallTab(): void
    {
        $user = $this->userWith(['nodes.view', 'nodes.create', 'nodes.install']);
        $location = Location::factory()->create();

        $response = $this->actingAs($user)->post('/admin/nodes/new', [
            'name' => 'Install Test', 'location_id' => $location->id, 'public' => 1, 'fqdn' => '203.0.113.30',
            'scheme' => 'http', 'behind_proxy' => 0, 'memory' => 1024, 'memory_overallocate' => 0, 'disk' => 1024,
            'disk_overallocate' => 0, 'upload_size' => 100, 'daemonListen' => 8080, 'daemonSFTP' => 2022, 'daemonBase' => '/var/lib/hydra/volumes',
        ]);

        $node = Node::query()->where('name', 'Install Test')->firstOrFail();
        $response->assertRedirect("/admin/nodes/view/$node->id/install");
    }
}
