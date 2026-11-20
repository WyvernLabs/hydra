<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\HydraServerMod;
use Pterodactyl\Models\HydraModpackInstall;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Services\Hydra\Minecraft\McJarsClient;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Hydra\Minecraft\VersionInstallService;
use Pterodactyl\Services\Hydra\Minecraft\Modpacks\CurseForgePackSource;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: modpack installer (addon "modpacks"). The node is faked as a file
 * tree in memory, Modrinth, CurseForge and mcjars with recorded answers.
 */
class ModpackInstallTest extends ClientApiIntegrationTestCase
{
    /** @var array<string, string> path (without leading slash) => "dir" or "file" */
    private array $fs = [];

    private array $pulls = [];

    private array $installs = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.modpacks.enabled', true);
        config()->set('hydra.addons.modpacks.curseforge', false);
        config()->set('hydra.addons.trash.enabled', false);
        config()->set('hydra.addons.games.enabled', false);
        Cache::flush();
        $this->pulls = $this->installs = [];
        $this->fs = [
            'config' => 'dir', 'config/old.toml' => 'file',
            'mods' => 'dir', 'mods/oldmod.jar' => 'file',
            'world' => 'dir', 'world/level.dat' => 'file',
        ];

        $index = [
            'formatVersion' => 1, 'game' => 'minecraft', 'versionId' => '1.0.0', 'name' => 'Test Pack',
            'dependencies' => ['minecraft' => '1.21.1', 'fabric-loader' => '0.16.9'],
            'files' => [
                ['path' => 'mods/sodium.jar', 'hashes' => ['sha1' => 'aaa'], 'env' => ['client' => 'required', 'server' => 'optional'],
                    'downloads' => ['https://cdn.modrinth.com/data/SOD/versions/s1/sodium.jar'], 'fileSize' => 10],
                ['path' => 'mods/client-only.jar', 'hashes' => ['sha1' => 'bbb'], 'env' => ['client' => 'required', 'server' => 'unsupported'],
                    'downloads' => ['https://cdn.modrinth.com/data/CLI/versions/c1/client-only.jar'], 'fileSize' => 10],
                ['path' => 'mods/evil.jar', 'hashes' => ['sha1' => 'ccc'], 'env' => ['server' => 'required'],
                    'downloads' => ['https://evil.example/evil.jar'], 'fileSize' => 10],
                ['path' => '../escape.jar', 'hashes' => ['sha1' => 'ddd'], 'downloads' => ['https://cdn.modrinth.com/x.jar']],
                ['path' => 'config/extra.json', 'hashes' => ['sha1' => 'eee'], 'downloads' => ['https://github.com/a/b/raw/extra.json']],
            ],
        ];
        $pack = $this->zip(['modrinth.index.json' => json_encode($index), 'overrides/config/pack.toml' => 'x=1']);

        Http::fake([
            'api.modrinth.com/v2/project/PACK/version*' => Http::response([[
                'id' => 'pv1', 'version_number' => '1.0.0', 'version_type' => 'release', 'date_published' => '2026-11-01T00:00:00Z',
                'game_versions' => ['1.21.1'], 'loaders' => ['fabric'],
                'files' => [['primary' => true, 'filename' => 'Test Pack 1.0.0.mrpack', 'size' => 100, 'url' => 'https://cdn.modrinth.com/data/PACK/versions/pv1/pack.mrpack']],
            ]]),
            'api.modrinth.com/v2/project/PACK' => Http::response(['id' => 'PACK', 'slug' => 'test-pack', 'title' => 'Test Pack', 'icon_url' => null]),
            'cdn.modrinth.com/data/PACK/versions/pv1/pack.mrpack' => Http::response($pack),
            'api.modrinth.com/v2/version_files' => Http::response(['aaa' => ['id' => 's1', 'project_id' => 'SOD', 'version_number' => '0.6.0']]),
            'api.modrinth.com/v2/projects*' => Http::response([['id' => 'SOD', 'slug' => 'sodium', 'title' => 'Sodium', 'icon_url' => null]]),
            'api.modrinth.com/v2/search*' => Http::response(['total_hits' => 1, 'hits' => [[
                'project_id' => 'PACK', 'slug' => 'test-pack', 'title' => 'Test Pack', 'author' => 'me', 'description' => 'Pack',
                'categories' => ['fabric', 'technology'], 'display_categories' => ['fabric', 'technology'], 'downloads' => 5, 'versions' => ['1.21.1'],
            ]]]),
            'api.modrinth.com/v2/tag/category' => Http::response([
                ['name' => 'technology', 'project_type' => 'modpack', 'header' => 'categories'],
                ['name' => 'optimization', 'project_type' => 'mod', 'header' => 'categories'],
            ]),
            'api.modrinth.com/v2/tag/game_version' => Http::response([['version' => '1.21.1', 'version_type' => 'release']]),
        ]);

        $this->mock(McJarsClient::class, function (MockInterface $mock) {
            $mock->allows('builds')->with('FABRIC', '1.21.1')->andReturn([
                ['id' => 5, 'projectVersionId' => '0.17.0', 'experimental' => false],
                ['id' => 4, 'projectVersionId' => '0.16.9', 'experimental' => false],
            ]);
        });
        $this->mock(VersionInstallService::class, function (MockInterface $mock) {
            $mock->allows('install')->andReturnUsing(function ($server, int $build, bool $wipe, bool $eula) {
                $this->installs[] = [$build, $wipe, $eula];

                return ['type' => 'FABRIC', 'version' => '1.21.1', 'build' => '0.16.9', 'build_id' => $build, 'java' => 21, 'image_changed' => false];
            });
        });

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $ok = new \GuzzleHttp\Psr7\Response(204);
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('getDirectory')->andReturnUsing(function (string $path) {
                $path = trim($path, '/');
                $list = [];
                foreach ($this->fs as $entry => $type) {
                    if (($path === '' ? !str_contains($entry, '/') : dirname($entry) === $path)) {
                        $list[] = ['name' => basename($entry), 'directory' => $type === 'dir', 'file' => $type === 'file', 'size' => 1];
                    }
                }

                return $list;
            });
            $mock->allows('createDirectory')->andReturnUsing(function (string $name, string $path) use ($ok) {
                $full = trim(trim($path, '/') . '/' . $name, '/');
                $parts = '';
                foreach (explode('/', $full) as $part) {
                    $parts = ltrim($parts . '/' . $part, '/');
                    $this->fs[$parts] = 'dir';
                }

                return $ok;
            });
            $mock->allows('pull')->andReturnUsing(function (string $url, string $dir, array $params) use ($ok) {
                $this->pulls[] = [$url, $dir, $params['filename']];
                $this->fs[trim(trim($dir, '/') . '/' . $params['filename'], '/')] = 'file';

                return $ok;
            });
            $mock->allows('decompressFile')->andReturnUsing(function (string $root, string $file) use ($ok) {
                $base = trim($root, '/');
                foreach (['overrides', 'overrides/config', 'overrides/mods', 'server-overrides', 'client-overrides'] as $dir) {
                    $this->fs["$base/$dir"] = 'dir';
                }
                foreach (['overrides/config/pack.toml', 'overrides/mods/bundled.jar', 'server-overrides/server.properties', 'client-overrides/options.txt'] as $entry) {
                    $this->fs["$base/$entry"] = 'file';
                }

                return $ok;
            });
            $mock->allows('renameFiles')->andReturnUsing(function ($root, array $files) use ($ok) {
                foreach ($files as $move) {
                    $from = trim($move['from'], '/');
                    $to = trim($move['to'], '/');
                    $this->assertArrayNotHasKey($to, $this->fs, "rename onto existing $to");
                    foreach ($this->fs as $entry => $type) {
                        if ($entry === $from || str_starts_with($entry, $from . '/')) {
                            unset($this->fs[$entry]);
                            $this->fs[$to . substr($entry, strlen($from))] = $type;
                        }
                    }
                }

                return $ok;
            });
            $mock->allows('deleteFiles')->andReturnUsing(function ($root, array $files) use ($ok) {
                foreach ($files as $file) {
                    $path = trim(trim((string) $root, '/') . '/' . $file, '/');
                    foreach (array_keys($this->fs) as $entry) {
                        if ($entry === $path || str_starts_with($entry, $path . '/')) {
                            unset($this->fs[$entry]);
                        }
                    }
                }

                return $ok;
            });
        });
    }

    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hydra-test');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $content = file_get_contents($path);
        @unlink($path);

        return $content;
    }

    private function body(array $extra = []): array
    {
        return array_merge(['source' => 'modrinth', 'project' => 'PACK', 'version_id' => 'pv1', 'mode' => 'wipe', 'eula' => true], $extra);
    }

    public function testPageAndSearch(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_MODPACKS_READ]);

        $this->actingAs($user)->getJson($this->link($server, '/modpacks'))
            ->assertOk()
            ->assertJsonPath('sources.0.categories.*.key', ['technology'])
            ->assertJsonPath('current', null)
            ->assertJsonPath('install', null);
        $this->actingAs($user)->getJson($this->link($server, '/modpacks/search?source=modrinth&loader=fabric&category=technology'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Test Pack')
            ->assertJsonPath('data.0.loaders', ['fabric'])
            ->assertJsonPath('data.0.categories', ['technology']);
        $this->actingAs($user)->getJson($this->link($server, '/modpacks/projects/modrinth/PACK/versions'))
            ->assertOk()
            ->assertJsonPath('data.0.name', '1.0.0')
            ->assertJsonMissingPath('data.0.url');

        // Reading is not installing; other eggs and a switched off addon get nothing.
        $this->actingAs($user)->postJson($this->link($server, '/modpacks/install'), $this->body())->assertForbidden();
        app(ServerNavigation::class)->setButtonEggs('modpacks', [Egg::query()->where('id', '!=', $server->egg_id)->value('id')]);
        $this->actingAs($user)->getJson($this->link($server, '/modpacks'))->assertNotFound();
        app(ServerNavigation::class)->setButtonEggs('modpacks', null);
        config()->set('hydra.addons.modpacks.enabled', false);
        $this->actingAs($user)->getJson($this->link($server, '/modpacks'))->assertNotFound();
    }

    public function testInstallWipesAndInstallsTheExactServerSoftware(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/modpacks/install'), $this->body())
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.result.minecraft', '1.21.1')
            ->assertJsonPath('data.result.loader', 'fabric')
            ->assertJsonPath('data.result.exact_build', true)
            ->assertJsonPath('data.total', 2);

        // mcjars build 4 is Fabric loader 0.16.9, wiped, EULA accepted.
        $this->assertSame([[4, true, true]], $this->installs);

        $this->assertSame([
            ['https://cdn.modrinth.com/data/PACK/versions/pv1/pack.mrpack', '/.hydra-modpack', 'pack.zip'],
            ['https://cdn.modrinth.com/data/SOD/versions/s1/sodium.jar', '/mods', 'sodium.jar'],
            ['https://github.com/a/b/raw/extra.json', '/config', 'extra.json'],
        ], $this->pulls);

        // Overrides and server overrides merged in, client overrides left out, work folder gone.
        foreach (['config/old.toml', 'config/pack.toml', 'config/extra.json', 'mods/bundled.jar', 'mods/sodium.jar', 'server.properties'] as $path) {
            $this->assertArrayHasKey($path, $this->fs, $path);
        }
        $this->assertArrayNotHasKey('options.txt', $this->fs);
        $this->assertEmpty(array_filter(array_keys($this->fs), fn ($path) => str_starts_with($path, '.hydra-modpack')));
        $this->assertArrayNotHasKey('escape.jar', $this->fs);

        $install = HydraModpackInstall::query()->where('server_id', $server->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(['client-only.jar', 'evil.jar'], $install->result['skipped']);

        // The mod manager knows Sodium; the server remembers the pack.
        $record = HydraServerMod::query()->where('server_id', $server->id)->sole();
        $this->assertSame(['sodium.jar', 'SOD', 's1', 'fabric'], [$record->file, $record->project_id, $record->version_id, $record->loader]);
        $this->assertSame('Test Pack', $server->refresh()->hydra_minecraft['modpack']['name']);
        $this->actingAs($user)->getJson($this->link($server, '/modpacks'))->assertJsonPath('current.version_id', 'pv1');

        $this->assertDatabaseHas('activity_logs', ['event' => 'server:modpacks.install']);
    }

    public function testKeepModeOnlyEmptiesTheModsFolder(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/modpacks/install'), $this->body(['mode' => 'keep', 'eula' => false]))
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->assertSame([[4, false, false]], $this->installs);
        $this->assertArrayNotHasKey('mods/oldmod.jar', $this->fs);
        $this->assertArrayHasKey('world/level.dat', $this->fs);
    }

    public function testOnlyOneInstallAtATimeAndProfilesNeedTheGamesAddon(): void
    {
        [$user, $server] = $this->generateTestAccount();
        HydraModpackInstall::query()->create([
            'server_id' => $server->id, 'source' => 'modrinth', 'project_id' => 'PACK', 'version_id' => 'pv1',
            'name' => 'Test Pack', 'mode' => 'wipe', 'status' => 'running',
        ]);

        $this->actingAs($user)->postJson($this->link($server, '/modpacks/install'), $this->body())
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', __('server/modpacks.errors.running'));

        // A run that stopped reporting long ago counts as failed and is no obstacle.
        HydraModpackInstall::query()->update(['updated_at' => now()->subHour()]);
        $this->actingAs($user)->getJson($this->link($server, '/modpacks/status'))->assertJsonPath('data.status', 'failed');
        $this->actingAs($user)->postJson($this->link($server, '/modpacks/install'), $this->body(['mode' => 'profile']))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', __('server/modpacks.errors.no_profiles'));
    }

    public function testFailuresAreShown(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->mock(McJarsClient::class, fn (MockInterface $mock) => $mock->allows('builds')->andThrow(new \Pterodactyl\Exceptions\DisplayException('x')));

        $this->actingAs($user)->postJson($this->link($server, '/modpacks/install'), $this->body())
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error', __('server/modpacks.errors.no_server', ['loader' => 'FABRIC', 'version' => '1.21.1']));
        $this->assertSame([], $this->installs);
    }

    public function testCurseForgePlan(): void
    {
        config()->set('hydra.addons.mods.curseforge_key', 'mods-key');
        $manifest = [
            'manifestType' => 'minecraftModpack',
            'minecraft' => ['version' => '1.20.1', 'modLoaders' => [['id' => 'forge-47.2.0', 'primary' => true]]],
            'files' => [
                ['projectID' => 1, 'fileID' => 11, 'required' => true],
                ['projectID' => 2, 'fileID' => 22, 'required' => true],
                ['projectID' => 3, 'fileID' => 33, 'required' => true],
                ['projectID' => 4, 'fileID' => 44, 'required' => true],
                ['projectID' => 5, 'fileID' => 55, 'required' => false],
            ],
            'overrides' => 'overrides',
        ];
        $path = tempnam(sys_get_temp_dir(), 'hydra-test');
        file_put_contents($path, $this->zip(['manifest.json' => json_encode($manifest)]));

        Http::fake([
            'api.curseforge.com/v1/mods/files' => Http::response(['data' => [
                ['id' => 11, 'modId' => 1, 'displayName' => 'Create 0.5', 'fileName' => 'create.jar', 'downloadUrl' => 'https://edge.forgecdn.net/files/1/1/create.jar', 'gameVersions' => ['1.20.1', 'Forge', 'Server', 'Client']],
                ['id' => 22, 'modId' => 2, 'displayName' => 'Zoom', 'fileName' => 'zoom.jar', 'downloadUrl' => 'https://edge.forgecdn.net/files/2/2/zoom.jar', 'gameVersions' => ['1.20.1', 'Client']],
                ['id' => 33, 'modId' => 3, 'displayName' => 'Locked', 'fileName' => 'locked.jar', 'downloadUrl' => null, 'gameVersions' => ['1.20.1']],
                ['id' => 44, 'modId' => 4, 'displayName' => 'Textures', 'fileName' => 'tex.zip', 'downloadUrl' => 'https://edge.forgecdn.net/files/4/4/tex.zip', 'gameVersions' => ['1.20.1']],
            ]]),
            'api.curseforge.com/v1/mods' => Http::response(['data' => [
                ['id' => 1, 'name' => 'Create', 'slug' => 'create', 'classId' => 6, 'links' => ['websiteUrl' => 'https://www.curseforge.com/minecraft/mc-mods/create']],
                ['id' => 2, 'name' => 'Zoom', 'slug' => 'zoom', 'classId' => 6],
                ['id' => 3, 'name' => 'Locked', 'slug' => 'locked', 'classId' => 6],
                ['id' => 4, 'name' => 'Textures', 'slug' => 'tex', 'classId' => 12],
            ]]),
        ]);

        $plan = app(CurseForgePackSource::class)->plan($path);
        @unlink($path);

        $this->assertSame(['1.20.1', 'forge', '47.2.0', ['overrides']], [$plan['minecraft'], $plan['loader'], $plan['loader_version'], $plan['overrides']]);
        $this->assertSame([['mods/create.jar', 'https://edge.forgecdn.net/files/1/1/create.jar', '1', '11']], array_map(
            fn ($file) => [$file['path'], $file['url'], $file['record']['project_id'], $file['record']['version_id']],
            $plan['files']
        ));
        $this->assertEqualsCanonicalizing(['Zoom', 'Textures'], $plan['skipped']);
        $this->assertSame([['name' => 'Locked', 'url' => 'https://www.curseforge.com/minecraft/mc-mods/locked/files/33']], $plan['manual']);
        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'mods-key'));
    }
}
