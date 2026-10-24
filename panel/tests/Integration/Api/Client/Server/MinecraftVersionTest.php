<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonProfileRepository;
use Pterodactyl\Services\Hydra\Minecraft\VersionInstallService;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: Minecraft version changer (addon "versions").
 */
class MinecraftVersionTest extends ClientApiIntegrationTestCase
{
    private array $calls = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.versions.enabled', true);
        Cache::flush();
        $this->calls = [];

        Http::fake([
            'mcjars.app/api/v2/types' => Http::response(['success' => true, 'types' => [
                'recommended' => [
                    'PAPER' => ['name' => 'Paper', 'icon' => 'https://s3.mcjars.app/icons/paper.png', 'builds' => 2, 'versions' => ['minecraft' => 1, 'project' => 0]],
                    'FORGE' => ['name' => 'Forge', 'builds' => 1, 'versions' => ['minecraft' => 1, 'project' => 1]],
                ],
            ]]),
            'mcjars.app/api/v2/builds/PAPER' => Http::response(['success' => true, 'builds' => [
                '1.21.4' => ['type' => 'RELEASE', 'supported' => true, 'java' => 21, 'builds' => 2],
            ]]),
            'mcjars.app/api/v2/builds/FORGE' => Http::response(['success' => true, 'builds' => [
                '1.20.1' => ['type' => 'RELEASE', 'supported' => true, 'java' => 17, 'builds' => 1],
            ]]),
            'mcjars.app/api/v1/build/232' => Http::response(['success' => true, 'build' => [
                'id' => 232, 'type' => 'PAPER', 'versionId' => '1.21.4', 'name' => '#232',
                'installation' => [[['type' => 'download', 'url' => 'https://fill-data.papermc.io/paper.jar', 'file' => 'server.jar', 'size' => 1]]],
            ]]),
            'mcjars.app/api/v1/build/7' => Http::response(['success' => true, 'build' => [
                'id' => 7, 'type' => 'FORGE', 'versionId' => '1.20.1', 'name' => '47.4.0',
                'installation' => [
                    [['type' => 'download', 'url' => 'https://files.mcjars.app/forge.zip', 'file' => 'mcvapi.server.jar.zip', 'size' => 1], ['type' => 'remove', 'location' => 'libraries']],
                    [['type' => 'unzip', 'file' => 'mcvapi.server.jar.zip', 'location' => '.']],
                    [['type' => 'remove', 'location' => 'mcvapi.server.jar.zip']],
                ],
            ]]),
            'mcjars.app/api/v1/build/666' => Http::response(['success' => true, 'build' => [
                'id' => 666, 'type' => 'PAPER', 'versionId' => '1.21.4', 'name' => 'evil',
                'installation' => [[['type' => 'download', 'url' => 'https://evil.example/x', 'file' => '../../etc/cron.d/x', 'size' => 1]]],
            ]]),
        ]);

        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('getDetails')->andReturn(['state' => 'offline']);
        });
        $this->mock(DaemonProfileRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('wipe')->andReturnUsing(fn () => $this->calls[] = ['wipe']);
        });
        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $response = new \GuzzleHttp\Psr7\Response(204);
            $mock->allows('pull')->andReturnUsing(function ($url, $dir, $params) use ($response) {
                $this->calls[] = ['pull', $url, $dir, $params['filename']];

                return $response;
            });
            $mock->allows('decompressFile')->andReturnUsing(function ($root, $file) use ($response) {
                $this->calls[] = ['unzip', $root, $file];

                return $response;
            });
            $mock->allows('deleteFiles')->andReturnUsing(function ($root, $files) use ($response) {
                $this->calls[] = ['remove', $root, $files];

                return $response;
            });
            $mock->allows('renameFiles')->andReturnUsing(function ($root, $files) use ($response) {
                $this->calls[] = ['rename', $root, $files];

                return $response;
            });
            $mock->allows('putContent')->andReturnUsing(function ($file, $content) use ($response) {
                $this->calls[] = ['write', $file, trim(explode("\n", $content)[1] ?? '')];

                return $response;
            });
        });
    }

    public function testListsAreOnlyShownWithPermissionAndAddon(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);
        $this->actingAs($user)->getJson($this->link($server, '/versions'))->assertForbidden();

        [$owner, $server] = $this->generateTestAccount();
        $this->actingAs($owner)->getJson($this->link($server, '/versions'))
            ->assertOk()
            ->assertJsonPath('categories.0.types.0.key', 'PAPER')
            ->assertJsonPath('categories.0.types.0.minecraft_versions', 1);
        $this->actingAs($owner)->getJson($this->link($server, '/versions/PAPER'))->assertOk()->assertJsonPath('data.0.java', 21);
        $this->actingAs($owner)->getJson($this->link($server, '/versions/NOPE'))->assertStatus(400);

        // Not offered for the egg of the server.
        app(ServerNavigation::class)->setButtonEggs('versions', [Egg::query()->where('id', '!=', $server->egg_id)->value('id')]);
        $this->actingAs($owner)->getJson($this->link($server, '/versions'))->assertNotFound();
        app(ServerNavigation::class)->setButtonEggs('versions', null);

        config()->set('hydra.addons.versions.enabled', false);
        $this->actingAs($owner)->getJson($this->link($server, '/versions'))->assertNotFound();
    }

    public function testInstallsPaperWithEula(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/versions/install'), ['build' => 232, 'eula' => true])
            ->assertOk()
            ->assertJsonPath('version', '1.21.4');

        // The jar is saved under the name the egg starts (SERVER_JARFILE).
        $jar = $server->variables()->where('env_variable', 'SERVER_JARFILE')->first();
        $this->assertSame([
            ['pull', 'https://fill-data.papermc.io/paper.jar', '/', basename($jar->server_value ?? $jar->default_value ?? 'server.jar')],
            ['write', '/eula.txt', 'eula=true'],
        ], $this->calls);
        $this->assertSame('PAPER', $server->refresh()->hydra_minecraft['type']);
        $this->assertSame('#232', $server->hydra_minecraft['build']);
    }

    public function testInstallsForgeOnAnEmptyServer(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/versions/install'), ['build' => 7, 'wipe' => true])->assertOk();

        $expected = [
            ['wipe'],
            ['pull', 'https://files.mcjars.app/forge.zip', '/', 'mcvapi.server.jar.zip'],
            ['remove', '/', ['libraries']],
            ['unzip', '/', 'mcvapi.server.jar.zip'],
            ['remove', '/', ['mcvapi.server.jar.zip']],
        ];
        // The unpacked server.jar is renamed to the jar file of the egg.
        $variable = $server->variables()->where('env_variable', 'SERVER_JARFILE')->first();
        $jar = basename($variable->server_value ?? $variable->default_value ?? 'server.jar');
        if ($jar !== 'server.jar') {
            $expected[] = ['remove', '/', [$jar]];
            $expected[] = ['rename', '/', [['from' => 'server.jar', 'to' => $jar]]];
        }
        $this->assertSame($expected, $this->calls);
    }

    public function testBuildsLeavingTheServerAreRefused(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/versions/install'), ['build' => 666])->assertStatus(400);
        $this->assertSame([], $this->calls);
    }

    public function testDockerImageMatchesJava(): void
    {
        [, $server] = $this->generateTestAccount();
        $original = $server->egg->docker_images;
        $server->egg->forceFill(['docker_images' => [
            'Java 8' => 'ghcr.io/pterodactyl/yolks:java_8',
            'Java 17' => 'ghcr.io/pterodactyl/yolks:java_17',
            'Java 21' => 'ghcr.io/pterodactyl/yolks:java_21',
        ]])->save();
        $service = app(VersionInstallService::class);

        $this->assertSame('ghcr.io/pterodactyl/yolks:java_17', $service->imageFor($server->refresh(), 17));
        $this->assertSame('ghcr.io/pterodactyl/yolks:java_17', $service->imageFor($server, 11));
        $this->assertSame('ghcr.io/pterodactyl/yolks:java_21', $service->imageFor($server, 25));
        $this->assertNull($service->imageFor($server, null));

        $server->egg->forceFill(['docker_images' => $original])->save();
    }
}
