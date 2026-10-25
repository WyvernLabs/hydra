<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\AdminRole;
use Pterodactyl\Services\Hydra\Api\ApiDocumentation;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: the API documentation is read from the routes and has to describe
 * every endpoint in every language.
 */
class ApiDocumentationTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    public function testEveryEndpointAndGroupIsDocumented(): void
    {
        $documentation = $this->app->make(ApiDocumentation::class);

        foreach (['en', 'de'] as $locale) {
            $this->app->setLocale($locale);
            foreach (ApiDocumentation::APIS as $api) {
                $missing = [];
                foreach ($documentation->groups($api) as $group => $endpoints) {
                    if (!is_array(trans("admin/api_docs.groups.$api.$group"))) {
                        $missing[] = "group $group";
                    }
                    foreach ($endpoints as $endpoint) {
                        if (is_null($endpoint['description'])) {
                            $missing[] = $endpoint['key'];
                        }
                    }
                }

                $this->assertSame([], $missing, "Undocumented in lang/$locale/admin/api_docs.php ($api). Add them under \"endpoints\" or \"groups\".");
            }
        }
    }

    public function testDescriptionsDoNotListRemovedEndpoints(): void
    {
        $documentation = $this->app->make(ApiDocumentation::class);

        foreach (ApiDocumentation::APIS as $api) {
            $keys = array_column($documentation->endpoints($api), 'key');
            foreach (['en', 'de'] as $locale) {
                $this->app->setLocale($locale);
                $stale = array_diff(array_keys(trans("admin/api_docs.endpoints.$api")), $keys);

                $this->assertSame([], array_values($stale), "lang/$locale/admin/api_docs.php describes endpoints that do not exist ($api).");
            }
        }
    }

    public function testPermissionsAndFieldsComeFromTheCode(): void
    {
        $documentation = $this->app->make(ApiDocumentation::class);
        $this->app->setLocale('en');

        $application = collect($documentation->endpoints('application'))->keyBy('key');
        $this->assertSame('servers: read', $application['GET servers']['permission']);
        $this->assertSame('servers: read & write · servers.create', $application['POST servers']['permission']);
        $this->assertSame('servers: read & write · servers.manage', $application['POST servers/{server}/suspend']['permission']);
        $this->assertArrayHasKey('egg', $application['POST servers']['fields']);

        $client = collect($documentation->endpoints('client'))->keyBy('key');
        $this->assertSame('file.read', $client['GET servers/{server}/files/list']['permission']);
        $this->assertSame('control.console', $client['POST servers/{server}/command']['permission']);
        $this->assertSame('servers/console', $documentation->group('client', 'servers/{server}/power'));
        $this->assertSame('servers/files', $documentation->group('client', 'servers/{server}/files/trash'));
        $this->assertSame('general', $documentation->group('client', ''));
    }

    public function testPageNeedsTheApiPermission(): void
    {
        $denied = User::factory()->create();
        $denied->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => ['nodes.view']]));
        $this->actingAs($denied)->get('/admin/api/docs')->assertForbidden();

        $allowed = User::factory()->create();
        $allowed->adminRoles()->attach(AdminRole::query()->create(['name' => 'Role ' . uniqid(), 'color' => '#3b9eea', 'permissions' => ['api.view']]));
        $this->actingAs($allowed)->get('/admin/api/docs')
            ->assertOk()
            ->assertSee('/api/client/servers/{server}/files/list', false)
            ->assertSee(trans('admin/api_docs.groups.client')['servers/files']['title']);
    }
}
