<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Mockery\MockInterface;
use Pterodactyl\Models\Permission;
use Pterodactyl\Services\Hydra\Games\GameSwitchService;
use Pterodactyl\Repositories\Wings\DaemonImportRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: import of another server over SFTP (addon "importer").
 */
class ImportTest extends ClientApiIntegrationTestCase
{
    private array $calls = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.importer.enabled', true);
        config()->set('hydra.addons.games.enabled', false);
        $this->calls = [];
        $this->mock(DaemonImportRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('status')->andReturn(['state' => '']);
            $mock->allows('test')->andReturnUsing(function (array $sftp, string $source) {
                $this->calls[] = ['test', $sftp, $source];

                return ['fingerprint' => 'SHA256:abc', 'folder' => '/home/container', 'entries' => 3, 'names' => ['world/']];
            });
            $mock->allows('start')->andReturnUsing(function (array $sftp, string $source, string $target) {
                $this->calls[] = ['start', $sftp, $source, $target];
            });
        });
    }

    private function login(array $extra = []): array
    {
        return array_merge([
            'host' => 'old.example.com',
            'port' => 2022,
            'username' => 'user.1234',
            'password' => 'hunter2',
            'source' => '/',
        ], $extra);
    }

    public function testConnectionIsTestedAndTheImportStarted(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import/test", $this->login())
            ->assertOk()
            ->assertJsonPath('fingerprint', 'SHA256:abc');
        $this->assertSame(['host' => 'old.example.com', 'port' => 2022, 'username' => 'user.1234', 'password' => 'hunter2', 'private_key' => ''], $this->calls[0][1]);

        // Without the host key of the test nothing starts.
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import", $this->login(['mode' => 'keep']))->assertStatus(422);
        // Keeping files as profile needs the game library.
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import", $this->login(['mode' => 'profile', 'host_key' => 'SHA256:abc']))->assertStatus(422);

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import", $this->login(['mode' => 'keep', 'host_key' => 'SHA256:abc', 'target' => 'imported']))
            ->assertStatus(202);
        $this->assertSame('start', $this->calls[1][0]);
        $this->assertSame('SHA256:abc', $this->calls[1][1]['host_key']);
        $this->assertSame('imported', $this->calls[1][3]);
        $this->assertActivityFor('server:import.start', $user, $server);
    }

    public function testCurrentFilesAreRemovedFirst(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->mock(GameSwitchService::class, function (MockInterface $mock) {
            $mock->expects('clear')->withArgs(fn ($s, bool $keep) => $keep === false)->once();
        });

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import", $this->login(['mode' => 'wipe', 'host_key' => 'SHA256:abc']))
            ->assertStatus(202);
    }

    public function testImportNeedsThePermissionAndTheAddon(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_CONTROL_CONSOLE]);
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import/test", $this->login())->assertForbidden();

        [$user, $server] = $this->generateTestAccount([Permission::ACTION_SETTINGS_IMPORT]);
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import/test", $this->login())->assertOk();

        // Subusers limited to some folders could write everywhere with an import.
        \Pterodactyl\Models\Subuser::query()->where('user_id', $user->id)->where('server_id', $server->id)->update(['allowed_paths' => json_encode(['/plugins'])]);
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import/test", $this->login())->assertForbidden();

        config()->set('hydra.addons.importer.enabled', false);
        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/import/test", $this->login())->assertNotFound();
    }
}
