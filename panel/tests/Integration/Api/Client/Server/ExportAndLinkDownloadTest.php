<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonExportRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: export to another server over SFTP (addon "exporter") and downloads
 * from a link (addon "link_download").
 */
class ExportAndLinkDownloadTest extends ClientApiIntegrationTestCase
{
    private array $calls = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.exporter.enabled', true);
        config()->set('hydra.addons.link_download.enabled', true);
        $this->calls = [];
        $this->mock(DaemonExportRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('status')->andReturn(['state' => '']);
            $mock->allows('testTarget')->andReturnUsing(function (array $sftp, string $source, string $target) {
                $this->calls[] = ['test', $sftp, $source, $target];

                return ['fingerprint' => 'SHA256:abc', 'folder' => '/backup', 'exists' => false, 'entries' => 0, 'names' => []];
            });
            $mock->allows('start')->andReturnUsing(function (array $sftp, string $source, string $target) {
                $this->calls[] = ['start', $sftp, $source, $target];
            });
        });
    }

    private function login(array $extra = []): array
    {
        return array_merge([
            'host' => 'new.example.com',
            'port' => 22,
            'username' => 'backup',
            'password' => 'hunter2',
            'source' => 'world/../plugins',
            'target' => 'backup',
        ], $extra);
    }

    public function testExportIsTestedAndStarted(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/export/test", $this->login())
            ->assertOk()
            ->assertJsonPath('exists', false);
        // The folder on this server is normalized, the one on the other server passed on.
        $this->assertSame(['/plugins', 'backup'], [$this->calls[0][2], $this->calls[0][3]]);

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/export", $this->login())->assertStatus(422);
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/export", $this->login(['host_key' => 'SHA256:abc']))
            ->assertStatus(202);
        $this->assertSame('start', $this->calls[1][0]);
        $this->assertSame('SHA256:abc', $this->calls[1][1]['host_key']);
        $this->assertActivityFor('server:export.start', $user, $server);
    }

    public function testExportNeedsItsPermissionAndTheWholeServer(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_SETTINGS_IMPORT]);
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/export/test", $this->login())->assertForbidden();

        [$user, $server] = $this->generateTestAccount([Permission::ACTION_SETTINGS_EXPORT]);
        Subuser::query()->where('server_id', $server->id)->where('user_id', $user->id)->update(['allowed_paths' => json_encode(['/plugins'])]);
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/export/test", $this->login())->assertForbidden();
        $this->assertEmpty($this->calls);
    }

    public function testAddonsCanBeSwitchedOff(): void
    {
        [$user, $server] = $this->generateTestAccount();
        config()->set('hydra.addons.exporter.enabled', false);
        config()->set('hydra.addons.link_download.enabled', false);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/export")->assertNotFound();
        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/files/pull")->assertNotFound();
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/files/pull", ['url' => 'https://example.com/a.zip'])->assertNotFound();
    }

    public function testDownloadsAreListedOnlyForAllowedFolders(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_CREATE, Permission::ACTION_FILE_READ]);
        Subuser::query()->where('server_id', $server->id)->where('user_id', $user->id)->update(['allowed_paths' => json_encode(['/plugins'])]);

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('pulls')->andReturn([
                'downloads' => [['Identifier' => 'a', 'Progress' => 0.5]],
                'hydra_finished' => [
                    ['identifier' => 'b', 'path' => '/plugins/WorldEdit.jar', 'success' => true],
                    ['identifier' => 'c', 'path' => '/world/secret.zip', 'success' => true],
                    ['identifier' => 'd', 'path' => '', 'success' => false, 'error' => 'status:404 Not Found'],
                ],
            ]);
            $mock->expects('cancelPull')->with('0f2c4b5e-9e8a-4a5f-9a43-1d2f3e4a5b6c')->once();
        });

        $data = $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/files/pull")->assertOk()->json();
        $this->assertSame([['identifier' => 'a', 'progress' => 0.5]], $data['downloads']);
        $this->assertSame(['b', 'd'], array_column($data['finished'], 'identifier'));

        $this->actingAs($user)->deleteJson("/api/client/servers/$server->uuid/files/pull/0f2c4b5e-9e8a-4a5f-9a43-1d2f3e4a5b6c")->assertNoContent();
    }

    public function testDownloadsNeedThePermissionToCreateFiles(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/files/pull")->assertForbidden();
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/files/pull", ['url' => 'https://example.com/a.zip'])->assertForbidden();
    }
}
