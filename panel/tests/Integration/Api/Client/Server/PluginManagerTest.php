<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\HydraServerPlugin;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: plugin manager (addon "plugins"). The node is faked with a plugins
 * folder in memory, the sources with recorded answers.
 */
class PluginManagerTest extends ClientApiIntegrationTestCase
{
    private bool $hasFolder = true;

    /** @var array<string, int> jar => size */
    private array $jars = [];

    private array $pulls = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.plugins.enabled', true);
        config()->set('hydra.addons.plugins.hangar', true);
        config()->set('hydra.addons.plugins.spigot', true);
        config()->set('hydra.addons.trash.enabled', false);
        Cache::flush();
        $this->hasFolder = true;
        $this->jars = [];
        $this->pulls = [];

        $version = fn (string $id, string $number, string $file) => [
            'id' => $id, 'version_number' => $number, 'version_type' => 'release', 'date_published' => '2026-11-01T00:00:00Z',
            'downloads' => 10, 'game_versions' => ['1.21.4'], 'loaders' => ['paper'],
            'files' => [['primary' => true, 'filename' => $file, 'size' => 1000, 'url' => "https://cdn.modrinth.com/data/LP/versions/$id/$file"]],
        ];
        Http::fake([
            'api.modrinth.com/v2/search*' => Http::response(['total_hits' => 1, 'hits' => [[
                'project_id' => 'LP', 'slug' => 'luckperms', 'title' => 'LuckPerms', 'author' => 'lucko', 'description' => 'Permissions',
                'icon_url' => 'https://cdn.modrinth.com/data/LP/icon.png', 'downloads' => 5000, 'date_modified' => '2026-11-01T00:00:00Z',
            ]]]),
            'api.modrinth.com/v2/project/LP/version*' => Http::response([$version('v2', '5.5.2', 'LuckPerms-5.5.2.jar'), $version('v1', '5.5.1', 'LuckPerms-5.5.1.jar')]),
            'api.modrinth.com/v2/project/LP' => Http::response(['id' => 'LP', 'slug' => 'luckperms', 'title' => 'LuckPerms', 'icon_url' => 'https://cdn.modrinth.com/data/LP/icon.png']),
            'api.modrinth.com/v2/project/EVIL/version*' => Http::response([array_merge($version('x', '1', '../../evil.sh'), [
                'files' => [['primary' => true, 'filename' => '../../evil.sh', 'size' => 1, 'url' => 'https://evil.example/evil.sh']],
            ])]),
            'api.modrinth.com/v2/project/EVIL' => Http::response(['id' => 'EVIL', 'title' => 'Evil']),
            'api.modrinth.com/v2/tag/game_version' => Http::response([
                ['version' => '1.21.4', 'version_type' => 'release'], ['version' => '25w01a', 'version_type' => 'snapshot'],
            ]),
            'api.spiget.org/v2/resources/123*' => Http::response([
                'id' => 123, 'name' => 'Paid', 'premium' => true, 'external' => false, 'file' => ['type' => '.jar'],
            ]),
            'cdn.modrinth.com/data/LP/icon.png' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('getDirectory')->andReturnUsing(function (string $path) {
                if ($path === '/') {
                    return $this->hasFolder ? [['name' => 'plugins', 'directory' => true, 'file' => false]] : [['name' => 'world', 'directory' => true]];
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
        return array_merge(['source' => 'modrinth', 'software' => 'paper', 'version' => '1.21.4'], $extra);
    }

    public function testPageNeedsAddonEggPermissionAndFolder(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);
        $this->actingAs($user)->getJson($this->link($server, '/plugins/available'))->assertForbidden();

        [$owner, $server] = $this->generateTestAccount();
        $this->actingAs($owner)->getJson($this->link($server, '/plugins/available'))->assertOk()->assertJsonPath('available', true);
        $this->actingAs($owner)->getJson($this->link($server, '/plugins'))
            ->assertOk()
            ->assertJsonPath('folder', 'plugins')
            ->assertJsonPath('sources.2.key', 'spigot')
            ->assertJsonPath('minecraft_versions', ['1.21.4']);

        // Without a plugins folder.
        $this->hasFolder = false;
        Cache::flush();
        $this->actingAs($owner)->getJson($this->link($server, '/plugins/available'))->assertOk()->assertJsonPath('available', false);
        $this->actingAs($owner)->getJson($this->link($server, '/plugins/installed'))->assertStatus(400);
        $this->hasFolder = true;

        // Not offered for the egg of the server.
        app(ServerNavigation::class)->setButtonEggs('plugins', [Egg::query()->where('id', '!=', $server->egg_id)->value('id')]);
        $this->actingAs($owner)->getJson($this->link($server, '/plugins'))->assertNotFound();
        app(ServerNavigation::class)->setButtonEggs('plugins', null);

        config()->set('hydra.addons.plugins.enabled', false);
        $this->actingAs($owner)->getJson($this->link($server, '/plugins'))->assertNotFound();
    }

    public function testSearchVersionsAndIcons(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_PLUGINS_READ]);

        $this->actingAs($user)->getJson($this->link($server, '/plugins/search?' . http_build_query($this->filters(['q' => 'luck']))))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'LuckPerms')
            ->assertJsonPath('more', false);
        $this->actingAs($user)->getJson($this->link($server, '/plugins/projects/modrinth/LP/versions?' . http_build_query($this->filters())))
            ->assertOk()
            ->assertJsonPath('data.0.name', '5.5.2')
            ->assertJsonMissingPath('data.0.url');

        // Icons only from the hosts of the sources.
        $this->actingAs($user)->get($this->link($server, '/plugins/icon?u=' . urlencode('https://cdn.modrinth.com/data/LP/icon.png')))
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($user)->get($this->link($server, '/plugins/icon?u=' . urlencode('https://169.254.169.254/latest')))->assertNotFound();
        $this->actingAs($user)->get($this->link($server, '/plugins/icon?u=' . urlencode('http://cdn.modrinth.com/x.png')))->assertNotFound();

        // Reading is not managing.
        $this->actingAs($user)->postJson($this->link($server, '/plugins/install'), $this->filters(['project' => 'LP']))->assertForbidden();
    }

    public function testInstallUpdateToggleAndDelete(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->jars = ['Manual.jar' => 50];

        // Older version first.
        $this->actingAs($user)->postJson($this->link($server, '/plugins/install'), $this->filters(['project' => 'LP', 'version_id' => 'v1']))
            ->assertOk()
            ->assertJsonPath('data.file', 'LuckPerms-5.5.1.jar');
        $this->assertSame([['https://cdn.modrinth.com/data/LP/versions/v1/LuckPerms-5.5.1.jar', '/plugins', 'LuckPerms-5.5.1.jar']], $this->pulls);

        $list = $this->actingAs($user)->getJson($this->link($server, '/plugins/installed'))->assertOk()->json('data');
        $this->assertSame(['LuckPerms-5.5.1.jar', 'Manual.jar'], array_column($list, 'file'));
        $this->assertSame('5.5.1', $list[0]['plugin']['version_name']);
        $this->assertNull($list[1]['plugin']);

        $updates = $this->actingAs($user)->getJson($this->link($server, '/plugins/updates?' . http_build_query($this->filters())))
            ->assertOk()
            ->json('data');
        $this->assertSame(['LuckPerms-5.5.1.jar' => ['version_id' => 'v2', 'version_name' => '5.5.2']], $updates);

        // Switched off, then updated: the new jar replaces the old one and stays off.
        $this->actingAs($user)->postJson($this->link($server, '/plugins/toggle'), ['file' => 'LuckPerms-5.5.1.jar', 'enabled' => false])->assertNoContent();
        $this->assertArrayHasKey('LuckPerms-5.5.1.jar.disabled', $this->jars);

        $this->actingAs($user)->postJson($this->link($server, '/plugins/install'), $this->filters(['project' => 'LP']))
            ->assertOk()
            ->assertJsonPath('data.version_name', '5.5.2');
        $this->assertSame(['Manual.jar', 'LuckPerms-5.5.2.jar.disabled'], array_keys($this->jars));
        $this->assertSame(1, HydraServerPlugin::query()->where('server_id', $server->id)->count());

        $this->actingAs($user)->postJson($this->link($server, '/plugins/toggle'), ['file' => 'LuckPerms-5.5.2.jar', 'enabled' => true])->assertNoContent();
        $this->actingAs($user)->postJson($this->link($server, '/plugins/delete'), ['file' => 'LuckPerms-5.5.2.jar'])->assertNoContent();
        $this->assertSame(['Manual.jar'], array_keys($this->jars));
        $this->assertSame(0, HydraServerPlugin::query()->where('server_id', $server->id)->count());

        // Paths cannot leave the folder.
        $this->actingAs($user)->postJson($this->link($server, '/plugins/delete'), ['file' => '../server.properties'])->assertStatus(400);

        $this->assertDatabaseHas('activity_logs', ['event' => 'server:plugins.update']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'server:plugins.delete']);
    }

    public function testUnsafeAndPremiumDownloadsAreRefused(): void
    {
        [$user, $server] = $this->generateTestAccount();

        // Downloads only from the hosts of the source; file names cannot leave the folder.
        $this->actingAs($user)->postJson($this->link($server, '/plugins/install'), $this->filters(['project' => 'EVIL']))->assertStatus(400);
        $this->assertSame([], $this->pulls);
        $this->assertSame('evil.sh.jar', \Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource::fileName('../../evil.sh', 'x'));

        $this->actingAs($user)->postJson($this->link($server, '/plugins/install'), $this->filters(['source' => 'spigot', 'project' => '123']))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', __('server/plugins.errors.premium'));

        // Disabled sources are not available.
        config()->set('hydra.addons.plugins.spigot', false);
        $this->actingAs($user)->postJson($this->link($server, '/plugins/install'), $this->filters(['source' => 'spigot', 'project' => '123']))->assertStatus(400);
    }
}
