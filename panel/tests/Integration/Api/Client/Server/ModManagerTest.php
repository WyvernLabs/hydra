<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Request;
use Pterodactyl\Models\HydraServerMod;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: mod manager (addon "mods"). The node is faked with a mods folder in
 * memory, Modrinth and CurseForge with recorded answers.
 */
class ModManagerTest extends ClientApiIntegrationTestCase
{
    private bool $hasFolder = true;

    /** @var string[] other entries in the root of the server */
    private array $root = [];

    /** @var array<string, int> jar => size */
    private array $jars = [];

    private array $pulls = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.mods.enabled', true);
        config()->set('hydra.addons.mods.curseforge', false);
        config()->set('hydra.addons.mods.curseforge_key', null);
        config()->set('hydra.addons.mods.dependencies', true);
        config()->set('hydra.addons.trash.enabled', false);
        Cache::flush();
        $this->hasFolder = true;
        $this->root = [];
        $this->jars = [];
        $this->pulls = [];

        $version = fn (string $project, string $id, string $number, string $file, array $dependencies = []) => [
            'id' => $id, 'version_number' => $number, 'version_type' => 'release', 'date_published' => '2026-11-01T00:00:00Z',
            'downloads' => 10, 'game_versions' => ['1.21.4'], 'loaders' => ['fabric'],
            'dependencies' => array_map(fn ($dependency) => ['project_id' => $dependency, 'version_id' => null, 'dependency_type' => 'required'], $dependencies),
            'files' => [['primary' => true, 'filename' => $file, 'size' => 1000, 'url' => "https://cdn.modrinth.com/data/$project/versions/$id/$file"]],
        ];
        $cfFile = fn (int $id, string $name, ?string $url) => [
            'id' => $id, 'displayName' => $name, 'fileName' => "$name.jar", 'releaseType' => 1, 'fileDate' => '2026-11-0' . ($id % 9 + 1) . 'T00:00:00Z',
            'fileLength' => 500, 'downloadCount' => 3, 'downloadUrl' => $url, 'gameVersions' => ['1.21.4', 'NeoForge', 'Server'],
            'isAvailable' => true, 'dependencies' => [],
        ];

        Http::fake([
            'api.modrinth.com/v2/search*' => Http::response(['total_hits' => 1, 'hits' => [[
                'project_id' => 'SOD', 'slug' => 'sodium', 'title' => 'Sodium', 'author' => 'jellysquid', 'description' => 'Fast',
                'icon_url' => 'https://cdn.modrinth.com/data/SOD/icon.png', 'downloads' => 5000, 'date_modified' => '2026-11-01T00:00:00Z',
                'categories' => ['optimization', 'fabric'], 'display_categories' => ['optimization', 'fabric'],
            ]]]),
            'api.modrinth.com/v2/tag/category' => Http::response([
                ['name' => 'optimization', 'project_type' => 'mod', 'header' => 'categories', 'icon' => '<svg/>'],
                ['name' => 'adventure', 'project_type' => 'mod', 'header' => 'categories', 'icon' => '<svg/>'],
                ['name' => 'fabric', 'project_type' => 'mod', 'header' => 'loaders', 'icon' => '<svg/>'],
                ['name' => 'minigame', 'project_type' => 'plugin', 'header' => 'categories', 'icon' => '<svg/>'],
            ]),
            'api.modrinth.com/v2/tag/game_version' => Http::response([
                ['version' => '1.21.4', 'version_type' => 'release'], ['version' => '25w01a', 'version_type' => 'snapshot'],
            ]),
            'api.modrinth.com/v2/project/SOD/version*' => Http::response([
                $version('SOD', 's2', '0.6.1', 'sodium-0.6.1.jar', ['FAPI']),
                $version('SOD', 's1', '0.6.0', 'sodium-0.6.0.jar', ['FAPI']),
            ]),
            'api.modrinth.com/v2/project/SOD' => Http::response(['id' => 'SOD', 'slug' => 'sodium', 'title' => 'Sodium', 'icon_url' => null]),
            'api.modrinth.com/v2/project/FAPI/version*' => Http::response([$version('FAPI', 'f1', '0.110.0', 'fabric-api-0.110.0.jar', ['SOD'])]),
            'api.modrinth.com/v2/project/FAPI' => Http::response(['id' => 'FAPI', 'slug' => 'fabric-api', 'title' => 'Fabric API']),
            'api.modrinth.com/v2/project/EVIL/version*' => Http::response([array_merge($version('EVIL', 'x', '1', 'evil.jar'), [
                'files' => [['primary' => true, 'filename' => '../../evil.sh', 'size' => 1, 'url' => 'https://evil.example/evil.sh']],
            ])]),
            'api.modrinth.com/v2/project/EVIL' => Http::response(['id' => 'EVIL', 'title' => 'Evil']),

            'api.curseforge.com/v1/categories*' => Http::response(['data' => [
                ['id' => 412, 'name' => 'Technology', 'slug' => 'technology', 'parentCategoryId' => 6, 'classId' => 6],
                ['id' => 415, 'name' => 'Energy', 'slug' => 'energy', 'parentCategoryId' => 412, 'classId' => 6],
            ]]),
            'api.curseforge.com/v1/mods/search*' => Http::response([
                'data' => [
                    ['id' => 100, 'name' => 'Mekanism', 'slug' => 'mekanism', 'summary' => 'Tech', 'downloadCount' => 9, 'authors' => [['name' => 'aidancbrady']],
                        'logo' => ['thumbnailUrl' => 'https://media.forgecdn.net/avatars/1.png'], 'links' => ['websiteUrl' => 'https://www.curseforge.com/minecraft/mc-mods/mekanism'],
                        'categories' => [['id' => 412]], 'allowModDistribution' => true],
                    ['id' => 200, 'name' => 'Locked', 'slug' => 'locked', 'summary' => 'No', 'downloadCount' => 1, 'categories' => [], 'allowModDistribution' => false],
                ],
                'pagination' => ['index' => 0, 'pageSize' => 20, 'resultCount' => 2, 'totalCount' => 2],
            ]),
            'api.curseforge.com/v1/mods/100/files*' => Http::response(['data' => [
                $cfFile(1001, 'Mekanism-10.7.0', 'https://edge.forgecdn.net/files/1/1/Mekanism-10.7.0.jar'),
            ]]),
            'api.curseforge.com/v1/mods/100' => Http::response(['data' => [
                'id' => 100, 'name' => 'Mekanism', 'slug' => 'mekanism', 'links' => ['websiteUrl' => 'https://www.curseforge.com/minecraft/mc-mods/mekanism'],
                'logo' => ['thumbnailUrl' => 'https://media.forgecdn.net/avatars/1.png'], 'allowModDistribution' => true,
            ]]),
            'api.curseforge.com/v1/mods/200/files*' => Http::response(['data' => [$cfFile(2001, 'Locked-1.0', null)]]),
            'api.curseforge.com/v1/mods/200' => Http::response(['data' => ['id' => 200, 'name' => 'Locked', 'slug' => 'locked', 'allowModDistribution' => false]]),
            'api.curseforge.com/v1/games/432' => Http::response(['data' => ['id' => 432]]),
        ]);

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('getDirectory')->andReturnUsing(function (string $path) {
                if ($path === '/') {
                    return array_merge(
                        $this->hasFolder ? [['name' => 'mods', 'directory' => true, 'file' => false]] : [['name' => 'world', 'directory' => true]],
                        array_map(fn ($name) => ['name' => $name, 'directory' => false, 'file' => true], $this->root)
                    );
                }

                return collect($this->jars)->map(fn ($size, $name) => ['name' => $name, 'directory' => false, 'file' => true, 'size' => $size])->values()->all();
            });
            $ok = new \GuzzleHttp\Psr7\Response(204);
            $mock->allows('pull')->andReturnUsing(function ($url, $dir, $params) use ($ok) {
                $this->pulls[] = [$url, $dir, $params['filename']];
                $this->jars[$params['filename']] = 1000;

                return $ok;
            });
            $mock->allows('renameFiles')->andReturnUsing(function ($root, $files) use ($ok) {
                foreach ($files as $file) {
                    $this->jars[$file['to']] = $this->jars[$file['from']];
                    unset($this->jars[$file['from']]);
                }

                return $ok;
            });
            $mock->allows('deleteFiles')->andReturnUsing(function ($root, $files) use ($ok) {
                foreach ($files as $file) {
                    unset($this->jars[$file]);
                }

                return $ok;
            });
        });
    }

    private function filters(array $extra = []): array
    {
        return array_merge(['source' => 'modrinth', 'loader' => 'fabric', 'version' => '1.21.4'], $extra);
    }

    private function enableCurseForge(): void
    {
        config()->set('hydra.addons.mods.curseforge', true);
        config()->set('hydra.addons.mods.curseforge_key', 'test-key');
    }

    public function testPageNeedsAddonEggPermissionAndFolder(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);
        $this->actingAs($user)->getJson($this->link($server, '/mods/available'))->assertForbidden();

        [$owner, $server] = $this->generateTestAccount();
        $this->root = ['fabric-server-launch.jar'];
        $this->actingAs($owner)->getJson($this->link($server, '/mods/available'))->assertOk()->assertJsonPath('available', true);
        $this->actingAs($owner)->getJson($this->link($server, '/mods'))
            ->assertOk()
            ->assertJsonPath('folder', 'mods')
            ->assertJsonCount(1, 'sources')
            ->assertJsonPath('sources.0.categories.*.key', ['adventure', 'optimization'])
            ->assertJsonPath('loaders', ['fabric', 'forge', 'neoforge', 'quilt'])
            ->assertJsonPath('minecraft_versions', ['1.21.4'])
            ->assertJsonPath('defaults.loader', 'fabric');

        // CurseForge only shows up with a key.
        config()->set('hydra.addons.mods.curseforge', true);
        $this->actingAs($owner)->getJson($this->link($server, '/mods'))->assertJsonCount(1, 'sources');
        $this->enableCurseForge();
        $this->actingAs($owner)->getJson($this->link($server, '/mods'))
            ->assertJsonPath('sources.1.key', 'curseforge')
            ->assertJsonPath('sources.1.categories.0.name', 'Energy')
            ->assertJsonPath('sources.1.categories.0.parent', '412');

        // Without a mods folder.
        $this->hasFolder = false;
        Cache::flush();
        $this->actingAs($owner)->getJson($this->link($server, '/mods/available'))->assertOk()->assertJsonPath('available', false);
        $this->actingAs($owner)->getJson($this->link($server, '/mods/installed'))->assertStatus(400);
        $this->hasFolder = true;

        // Not offered for the egg of the server.
        app(ServerNavigation::class)->setButtonEggs('mods', [Egg::query()->where('id', '!=', $server->egg_id)->value('id')]);
        $this->actingAs($owner)->getJson($this->link($server, '/mods'))->assertNotFound();
        app(ServerNavigation::class)->setButtonEggs('mods', null);

        config()->set('hydra.addons.mods.enabled', false);
        $this->actingAs($owner)->getJson($this->link($server, '/mods'))->assertNotFound();
    }

    public function testSearchByCategoryAndVersions(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_MODS_READ]);

        $this->actingAs($user)->getJson($this->link($server, '/mods/search?' . http_build_query($this->filters(['q' => 'sod', 'category' => 'optimization']))))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Sodium')
            ->assertJsonPath('data.0.categories', ['optimization'])
            ->assertJsonPath('more', false);
        Http::assertSent(function (Request $request) {
            if (!str_contains($request->url(), 'api.modrinth.com/v2/search')) {
                return false;
            }
            $facets = json_decode($request->data()['facets'] ?? '[]', true);

            return in_array(['categories:optimization'], $facets, true)
                && in_array(['categories:fabric'], $facets, true)
                && in_array(['server_side:required', 'server_side:optional'], $facets, true);
        });

        $this->actingAs($user)->getJson($this->link($server, '/mods/projects/modrinth/SOD/versions?' . http_build_query($this->filters())))
            ->assertOk()
            ->assertJsonPath('data.0.name', '0.6.1')
            ->assertJsonMissingPath('data.0.url')
            ->assertJsonMissingPath('data.0.dependencies');

        $this->actingAs($user)->getJson($this->link($server, '/mods/search?' . http_build_query($this->filters(['category' => '../x']))))->assertStatus(422);

        // Reading is not managing.
        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), $this->filters(['project' => 'SOD']))->assertForbidden();
    }

    public function testInstallWithDependenciesUpdateToggleAndDelete(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->jars = ['Manual.jar' => 50];

        // Older version first; Fabric API comes along (and its dependency back on Sodium is not installed twice).
        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), $this->filters(['project' => 'SOD', 'version_id' => 's1']))
            ->assertOk()
            ->assertJsonPath('data.file', 'sodium-0.6.0.jar')
            ->assertJsonPath('data.dependencies', ['Fabric API']);
        $this->assertSame(['sodium-0.6.0.jar', 'fabric-api-0.110.0.jar'], array_column($this->pulls, 2));
        $this->assertSame('/mods', $this->pulls[0][1]);
        $this->assertTrue(HydraServerMod::query()->where('project_id', 'FAPI')->first()->dependency);
        $this->assertFalse(HydraServerMod::query()->where('project_id', 'SOD')->first()->dependency);

        $list = $this->actingAs($user)->getJson($this->link($server, '/mods/installed'))->assertOk()->json('data');
        $this->assertSame(['fabric-api-0.110.0.jar', 'Manual.jar', 'sodium-0.6.0.jar'], array_column($list, 'file'));
        $this->assertTrue($list[0]['mod']['dependency']);
        $this->assertNull($list[1]['mod']);

        $updates = $this->actingAs($user)->getJson($this->link($server, '/mods/updates?' . http_build_query($this->filters())))->assertOk()->json('data');
        $this->assertSame(['sodium-0.6.0.jar' => ['version_id' => 's2', 'version_name' => '0.6.1']], $updates);

        // Switched off, then updated: the new jar replaces the old one and stays off.
        $this->actingAs($user)->postJson($this->link($server, '/mods/toggle'), ['file' => 'sodium-0.6.0.jar', 'enabled' => false])->assertNoContent();
        $this->assertArrayHasKey('sodium-0.6.0.jar.disabled', $this->jars);
        $this->pulls = [];
        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), $this->filters(['project' => 'SOD']))
            ->assertOk()
            ->assertJsonPath('data.version_name', '0.6.1')
            ->assertJsonPath('data.dependencies', []);
        $this->assertCount(1, $this->pulls);
        $this->assertArrayHasKey('sodium-0.6.1.jar.disabled', $this->jars);
        $this->assertArrayNotHasKey('sodium-0.6.0.jar.disabled', $this->jars);

        $this->actingAs($user)->postJson($this->link($server, '/mods/delete'), ['file' => 'sodium-0.6.1.jar'])->assertNoContent();
        $this->assertSame(['Manual.jar', 'fabric-api-0.110.0.jar'], array_keys($this->jars));
        $this->actingAs($user)->postJson($this->link($server, '/mods/delete'), ['file' => '../server.properties'])->assertStatus(400);

        $this->assertDatabaseHas('activity_logs', ['event' => 'server:mods.dependency']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'server:mods.update']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'server:mods.delete']);
    }

    public function testDependenciesCanBeSwitchedOff(): void
    {
        config()->set('hydra.addons.mods.dependencies', false);
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), $this->filters(['project' => 'SOD']))
            ->assertOk()
            ->assertJsonPath('data.dependencies', []);
        $this->assertSame(['sodium-0.6.1.jar'], array_column($this->pulls, 2));
    }

    public function testCurseForge(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $filters = $this->filters(['source' => 'curseforge', 'loader' => 'neoforge']);

        // Not available without a key.
        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), array_merge($filters, ['project' => '100']))->assertStatus(400);

        $this->enableCurseForge();
        $this->actingAs($user)->getJson($this->link($server, '/mods/search?' . http_build_query(array_merge($filters, ['category' => '412']))))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Mekanism')
            ->assertJsonPath('data.0.categories', ['412'])
            ->assertJsonPath('data.0.installable', true)
            ->assertJsonPath('data.1.installable', false)
            ->assertJsonPath('data.1.reason', 'external');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.curseforge.com/v1/mods/search')
            && $request->hasHeader('x-api-key', 'test-key')
            && ($request->data()['categoryId'] ?? null) == 412
            && ($request->data()['modLoaderType'] ?? null) == 6);

        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), array_merge($filters, ['project' => '100']))
            ->assertOk()
            ->assertJsonPath('data.file', 'Mekanism-10.7.0.jar');
        $this->assertSame('https://edge.forgecdn.net/files/1/1/Mekanism-10.7.0.jar', $this->pulls[0][0]);

        // Authors who only allow downloads on CurseForge get a link instead.
        $this->actingAs($user)->getJson($this->link($server, '/mods/projects/curseforge/200/versions?' . http_build_query($filters)))
            ->assertOk()
            ->assertJsonPath('data.0.installable', false)
            ->assertJsonPath('data.0.external_url', 'https://www.curseforge.com/minecraft/mc-mods/locked/files/2001');
        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), array_merge($filters, ['project' => '200']))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', __('server/mods.errors.external'));

        // Icons only from the hosts of the enabled sources.
        Http::fake(['media.forgecdn.net/*' => Http::response('PNG', 200, ['Content-Type' => 'image/png'])]);
        $this->actingAs($user)->get($this->link($server, '/mods/icon?u=' . urlencode('https://media.forgecdn.net/avatars/1.png')))->assertOk();
        $this->actingAs($user)->get($this->link($server, '/mods/icon?u=' . urlencode('https://169.254.169.254/latest')))->assertNotFound();
    }

    public function testUnsafeDownloadsAreRefused(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), $this->filters(['project' => 'EVIL']))->assertStatus(400);
        $this->assertSame([], $this->pulls);
        $this->actingAs($user)->postJson($this->link($server, '/mods/install'), $this->filters(['loader' => 'bukkit', 'project' => 'SOD']))->assertStatus(422);
    }
}
