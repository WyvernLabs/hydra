<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Mockery\MockInterface;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\HydraServerTemplate;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: templates for new servers (addon "server_templates").
 */
class ServerTemplateTest extends HttpTestCase
{
    private Egg $egg;

    private array $started = [];

    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the render time, which public/index.php normally starts.
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        config()->set('hydra.addons.server_templates.enabled', true);
        $this->egg = Egg::query()->where('author', 'support@pterodactyl.io')->where('name', 'Bungeecord')->firstOrFail();

        $this->started = [];
        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('create')->andReturnUsing(fn (bool $start) => $this->started[] = $start);
        });
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => $permissions]));

        return $user->refresh();
    }

    private function admin(): User
    {
        return User::factory()->create(['root_admin' => true]);
    }

    private function node(array $attributes = []): Node
    {
        $node = Node::factory()->create($attributes + ['location_id' => Location::factory()->create()->id, 'memory' => 65536, 'disk' => 1048576]);
        Allocation::factory()->create(['node_id' => $node->id, 'port' => 30000]);

        return $node;
    }

    private function form(array $values = []): array
    {
        return array_merge([
            'name' => 'Template ' . uniqid(),
            'description' => 'For tests',
            'egg_id' => $this->egg->id,
            'image' => array_values($this->egg->docker_images)[0],
            'environment' => ['SERVER_JARFILE' => 'custom.jar', 'BUNGEE_VERSION' => '1234'],
            'cpu' => 150,
            'memory' => 2048,
            'swap' => 0,
            'disk' => 10240,
            'io' => 400,
            'database_limit' => 2,
            'allocation_limit' => 3,
            'backup_storage_gb' => 2,
        ], $values);
    }

    private function template(array $values = []): HydraServerTemplate
    {
        return HydraServerTemplate::query()->create(array_merge([
            'name' => 'Template ' . uniqid(),
            'egg_id' => $this->egg->id,
            'image' => 'ghcr.io/example/image:1',
            'environment' => ['SERVER_JARFILE' => 'custom.jar', 'BUNGEE_VERSION' => '1234'],
            'cpu' => 150,
            'memory' => 2048,
            'swap' => 0,
            'disk' => 10240,
            'io' => 400,
            'database_limit' => 2,
            'allocation_limit' => 3,
            'backup_storage_limit' => 2048,
        ], $values));
    }

    public function testPagesAreGoneWhileTheAddonIsOff(): void
    {
        config()->set('hydra.addons.server_templates.enabled', false);

        $this->actingAs($this->admin())->get('/admin/servers/templates')->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/servers/new/template')->assertNotFound();
    }

    public function testManagingAndUsingTemplatesHaveTheirOwnPermissions(): void
    {
        $creator = $this->userWith(['servers.view', 'servers.create']);
        $this->actingAs($creator)->get('/admin/servers/new/template')->assertOk();
        $this->actingAs($creator)->get('/admin/servers/templates')->assertForbidden();
        $this->actingAs($creator)->post('/admin/servers/templates/new', $this->form())->assertForbidden();

        $manager = $this->userWith(['servers.view', 'servers.templates']);
        $this->actingAs($manager)->get('/admin/servers/templates')->assertOk();
        $this->actingAs($manager)->get('/admin/servers/templates/new')->assertOk();
        $this->actingAs($manager)->get('/admin/servers/new/template')->assertForbidden();
    }

    public function testTemplateIsCreatedEditedAndDeleted(): void
    {
        $admin = $this->admin();
        $node = $this->node(['public' => false]);

        $this->actingAs($admin)
            ->post('/admin/servers/templates/new', $this->form(['name' => $name = 'Small ' . uniqid(), 'nodes' => [$node->id], 'oom_enabled' => 1, 'start_on_completion' => 1]))
            ->assertRedirect('/admin/servers/templates');

        $template = HydraServerTemplate::query()->where('name', $name)->firstOrFail();
        $this->assertSame(2048, $template->backup_storage_limit);
        $this->assertSame([$node->id], $template->nodes);
        $this->assertFalse($template->oom_disabled);
        $this->assertTrue($template->start_on_completion);
        $this->assertSame('custom.jar', $template->environment['SERVER_JARFILE']);

        $this->actingAs($admin)->get("/admin/servers/templates/{$template->id}")->assertOk()->assertSee($name);
        $this->actingAs($admin)->get('/admin/servers/templates')->assertOk()->assertSee($name)->assertSee($node->name);

        $this->actingAs($admin)
            ->patch("/admin/servers/templates/{$template->id}", $this->form(['name' => $name, 'memory' => 4096, 'custom_image' => 'ghcr.io/example/own:2']))
            ->assertRedirect('/admin/servers/templates');
        $template->refresh();
        $this->assertSame(4096, $template->memory);
        $this->assertSame('ghcr.io/example/own:2', $template->image);
        $this->assertNull($template->nodes);
        $this->assertTrue($template->oom_disabled);

        $this->actingAs($admin)->delete("/admin/servers/templates/{$template->id}")->assertRedirect('/admin/servers/templates');
        $this->assertNull(HydraServerTemplate::query()->find($template->id));
    }

    public function testInvalidTemplatesAreRejected(): void
    {
        $admin = $this->admin();
        $taken = $this->template();

        $this->actingAs($admin)->post('/admin/servers/templates/new', $this->form(['name' => $taken->name]))->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'name');
        $this->actingAs($admin)->post('/admin/servers/templates/new', $this->form(['image' => null]))->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'image');
        $this->actingAs($admin)->post('/admin/servers/templates/new', $this->form(['custom_image' => 'bad;image']))->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'custom_image');
        $this->actingAs($admin)
            ->post('/admin/servers/templates/new', $this->form(['environment' => ['SERVER_JARFILE' => 'no spaces!', 'BUNGEE_VERSION' => '1234']]))
            ->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'environment.SERVER_JARFILE');
    }

    public function testServerIsCreatedWithOnlyNameAndOwner(): void
    {
        $node = $this->node();
        $owner = User::factory()->create();
        $template = $this->template(['locations' => [$node->location_id], 'start_on_completion' => true]);

        $response = $this->actingAs($this->userWith(['servers.view', 'servers.create']))->post('/admin/servers/new/template', [
            'name' => 'From template',
            'owner_id' => $owner->id,
            'template_id' => $template->id,
        ]);

        $server = Server::query()->where('owner_id', $owner->id)->firstOrFail();
        $response->assertRedirect("/admin/servers/view/{$server->id}");
        $this->assertSame('From template', $server->name);
        $this->assertSame($node->id, $server->node_id);
        $this->assertSame($this->egg->id, $server->egg_id);
        $this->assertSame($this->egg->nest_id, $server->nest_id);
        $this->assertSame('ghcr.io/example/image:1', $server->image);
        $this->assertSame($this->egg->startup, $server->startup);
        $this->assertSame(150, $server->cpu);
        $this->assertSame(2048, $server->memory);
        $this->assertSame(10240, $server->disk);
        $this->assertSame(400, $server->io);
        $this->assertSame(2, $server->database_limit);
        $this->assertSame(3, $server->allocation_limit);
        $this->assertSame(2048, $server->backup_storage_limit);
        $this->assertNotNull($server->allocation_id);
        $this->assertSame([true], $this->started);

        $variables = $server->variables->pluck('server_value', 'env_variable');
        $this->assertSame('custom.jar', $variables['SERVER_JARFILE']);
        $this->assertSame('1234', $variables['BUNGEE_VERSION']);
    }

    public function testTemplateCanTargetANonPublicNodeAndKeepsItsOwnStartup(): void
    {
        $node = $this->node(['public' => false]);
        $owner = User::factory()->create();
        $template = $this->template(['nodes' => [$node->id], 'startup' => 'java -jar own.jar']);

        $this->actingAs($this->admin())->post('/admin/servers/new/template', [
            'name' => 'Dedicated',
            'owner_id' => $owner->id,
            'template_id' => $template->id,
        ])->assertRedirect();

        $server = Server::query()->where('owner_id', $owner->id)->firstOrFail();
        $this->assertSame($node->id, $server->node_id);
        $this->assertSame('java -jar own.jar', $server->startup);
        $this->assertSame([false], $this->started);
    }

    public function testNameOwnerAndTemplateAreRequired(): void
    {
        $this->actingAs($this->admin())->post('/admin/servers/new/template', [])
            ->assertStatus(422)->assertJsonCount(3, 'errors');
    }

    public function testNoServerIsCreatedWithoutARoomyNode(): void
    {
        $node = $this->node(['memory' => 1024, 'memory_overallocate' => 0]);
        $owner = User::factory()->create();
        $template = $this->template(['nodes' => [$node->id]]);

        $this->actingAs($this->admin())
            ->post('/admin/servers/new/template', ['name' => 'Too big', 'owner_id' => $owner->id, 'template_id' => $template->id])
            ->assertStatus(400);

        $this->assertSame(0, Server::query()->where('owner_id', $owner->id)->count());
    }
}
