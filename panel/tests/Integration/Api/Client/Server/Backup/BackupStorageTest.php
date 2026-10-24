<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Backup;

use Mockery\MockInterface;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\SftpBackupTarget;
use Pterodactyl\Services\Backups\DeleteBackupService;
use Pterodactyl\Services\Backups\InitiateBackupService;
use Pterodactyl\Repositories\Wings\DaemonSftpRepository;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;
use Pterodactyl\Exceptions\Service\Backup\SftpBackupException;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: backups limited by their space, and backups on SFTP servers (addon
 * "sftp_backups").
 */
class BackupStorageTest extends ClientApiIntegrationTestCase
{
    private const MIB = 1024 * 1024;

    public function setUp(): void
    {
        parent::setUp();

        // The backups made by the factory would hit the throttle of new backups.
        config()->set('backups.throttles.period', 0);
    }

    public function testBackupIsRefusedWithoutSpace(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server->forceFill(['backup_storage_limit' => 0])->save();

        $this->actingAs($user)->postJson($this->link($server, '/backups'))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'This server has no space for backups.');
    }

    public function testBackupIsRefusedWhenTheSpaceIsFull(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server->forceFill(['backup_storage_limit' => 1])->save();
        Backup::factory()->create(['server_id' => $server->id, 'bytes' => 2 * self::MIB]);

        $this->actingAs($user)->postJson($this->link($server, '/backups'))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'The backup space of this server is full. Delete older backups first.');
    }

    public function testBackupMayUseTheSpaceThatIsLeft(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server->forceFill(['backup_storage_limit' => 10])->save();
        Backup::factory()->create(['server_id' => $server->id, 'bytes' => 4 * self::MIB]);
        // Failed backups and those on the user's own SFTP server do not count.
        Backup::factory()->create(['server_id' => $server->id, 'bytes' => 0, 'is_successful' => false]);
        $own = $this->ownTarget($server);
        Backup::factory()->create(['server_id' => $server->id, 'bytes' => 50 * self::MIB, 'disk' => Backup::ADAPTER_SFTP, 'hydra_sftp_target_id' => $own->id]);

        $this->mock(DaemonBackupRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->setBackupAdapter->backup')
                ->withArgs(fn (Backup $backup, int $maxSize) => $backup->disk === Backup::ADAPTER_WINGS && $maxSize === 6 * self::MIB);
        });

        $this->actingAs($user)->postJson($this->link($server, '/backups'))->assertOk();

        $this->actingAs($user)->getJson($this->link($server, '/backups'))
            ->assertOk()
            ->assertJsonPath('meta.storage_used', 4 * self::MIB)
            ->assertJsonPath('meta.storage_limit', 10 * self::MIB);
    }

    public function testScheduledBackupDeletesTheOldestBackups(): void
    {
        [, $server] = $this->generateTestAccount();
        $server->forceFill(['backup_storage_limit' => 10])->save();
        $oldest = Backup::factory()->create(['server_id' => $server->id, 'bytes' => 6 * self::MIB, 'created_at' => now()->subDays(2)]);
        Backup::factory()->create(['server_id' => $server->id, 'bytes' => 3 * self::MIB, 'created_at' => now()->subDay()]);

        $this->mock(DeleteBackupService::class, function (MockInterface $mock) use ($oldest) {
            $mock->expects('handle')
                ->withArgs(fn (Backup $backup) => $backup->id === $oldest->id)
                ->andReturnUsing(fn (Backup $backup) => $backup->delete());
        });
        $this->mock(DaemonBackupRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->setBackupAdapter->backup')
                ->withArgs(fn (Backup $backup, int $maxSize) => $maxSize === 7 * self::MIB);
        });

        $this->app->make(InitiateBackupService::class)->handle($server, null, true);

        $this->assertSoftDeleted('backups', ['id' => $oldest->id]);
    }

    public function testBackupGoesToTheOwnSftpServer(): void
    {
        config()->set('hydra.addons.sftp_backups.enabled', true);
        [$user, $server] = $this->generateTestAccount();
        // The own SFTP server does not need any backup space.
        $server->forceFill(['backup_storage_limit' => 0])->save();
        $target = $this->ownTarget($server);

        $this->mock(DaemonBackupRepository::class, function (MockInterface $mock) use ($target) {
            $mock->expects('setServer->setBackupAdapter->backup')
                ->withArgs(fn (Backup $backup, int $maxSize) => $backup->disk === Backup::ADAPTER_SFTP
                    && $backup->hydra_sftp_target_id === $target->id && $maxSize === 0);
        });

        $this->actingAs($user)->postJson($this->link($server, '/backups'), ['location' => 'own_sftp'])
            ->assertOk()
            ->assertJsonPath('attributes.location', 'own_sftp');

        // Without the addon the own server cannot be used.
        config()->set('hydra.addons.sftp_backups.enabled', false);
        $this->actingAs($user)->postJson($this->link($server, '/backups'), ['location' => 'own_sftp'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'No own SFTP server is set up for backups.');
    }

    public function testNodeSftpServerIsUsedAndCounted(): void
    {
        config()->set('hydra.addons.sftp_backups.enabled', true);
        [$user, $server] = $this->generateTestAccount();
        $server->forceFill(['backup_storage_limit' => 10])->save();
        $target = SftpBackupTarget::query()->create([
            'node_id' => $server->node_id,
            'enabled' => true,
            'host' => 'backup.example.com',
            'port' => 22,
            'username' => 'hydra',
            'password' => 'secret',
            'path' => 'backups',
            'host_key' => 'SHA256:node',
        ]);
        Backup::factory()->create(['server_id' => $server->id, 'bytes' => 3 * self::MIB, 'disk' => Backup::ADAPTER_SFTP, 'hydra_sftp_target_id' => $target->id]);

        $this->mock(DaemonBackupRepository::class, function (MockInterface $mock) use ($target) {
            $mock->expects('setServer->setBackupAdapter->backup')
                ->withArgs(fn (Backup $backup, int $maxSize) => $backup->hydra_sftp_target_id === $target->id && $maxSize === 7 * self::MIB);
        });

        $this->actingAs($user)->postJson($this->link($server, '/backups'))
            ->assertOk()
            ->assertJsonPath('attributes.location', 'node_sftp');

        // The target as sent to the node: an admin's target may be in a private network.
        $this->assertTrue($target->toDaemon()['allow_internal']);
        $this->assertFalse($this->ownTarget($server)->toDaemon()['allow_internal']);
    }

    public function testOwnSftpServerCanBeSetUp(): void
    {
        config()->set('hydra.addons.sftp_backups.enabled', true);
        [$user, $server] = $this->generateTestAccount();

        $this->mock(DaemonSftpRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->test')
                ->withArgs(fn (array $target) => $target['host'] === 'backup.example.com' && $target['password'] === 'secret' && $target['allow_internal'] === false)
                ->andReturn(['fingerprint' => 'SHA256:abc', 'changed' => false, 'files' => []]);
        });

        $this->actingAs($user)->putJson($this->link($server, '/backups/sftp'), [
            'host' => 'backup.example.com',
            'port' => 22,
            'username' => 'hydra',
            'password' => 'secret',
            'path' => 'backups',
        ])
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('host_key', 'SHA256:abc')
            ->assertJsonMissingPath('password');

        $target = $server->refresh()->sftpBackupTarget;
        $this->assertSame('secret', $target->password);
        // The password is stored encrypted.
        $this->assertNotSame('secret', $target->getRawOriginal('password'));

        $this->actingAs($user)->getJson($this->link($server, '/backups/sftp'))
            ->assertOk()
            ->assertJsonPath('has_password', true)
            ->assertJsonMissingPath('password');
    }

    public function testSubusersNeedThePermissionForTheSftpServer(): void
    {
        config()->set('hydra.addons.sftp_backups.enabled', true);
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_BACKUP_READ, Permission::ACTION_BACKUP_CREATE]);

        $this->actingAs($user)->putJson($this->link($server, '/backups/sftp'), [
            'host' => 'backup.example.com',
            'port' => 22,
            'username' => 'hydra',
            'password' => 'secret',
        ])->assertForbidden();
        $this->actingAs($user)->deleteJson($this->link($server, '/backups/sftp'))->assertForbidden();

        config()->set('hydra.addons.sftp_backups.enabled', false);
        $this->actingAs($user)->getJson($this->link($server, '/backups/sftp'))->assertNotFound();
    }

    public function testFailedConnectionTestSavesNothing(): void
    {
        config()->set('hydra.addons.sftp_backups.enabled', true);
        [$user, $server] = $this->generateTestAccount();

        $this->mock(DaemonSftpRepository::class, function (MockInterface $mock) {
            $mock->expects('setNode->test')->andThrow(new SftpBackupException('SFTP: could not log in to the SFTP server'));
        });

        $this->actingAs($user)->putJson($this->link($server, '/backups/sftp'), [
            'host' => 'backup.example.com',
            'port' => 22,
            'username' => 'hydra',
            'password' => 'wrong',
        ])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'SFTP server: could not log in to the SFTP server');

        $this->assertNull($server->refresh()->sftpBackupTarget);
    }

    public function testBackupsOnTheSftpServerCanBeImported(): void
    {
        config()->set('hydra.addons.sftp_backups.enabled', true);
        [$user, $server] = $this->generateTestAccount();
        $target = $this->ownTarget($server);

        $this->mock(DaemonSftpRepository::class, function (MockInterface $mock) {
            $mock->allows('setNode->files')->andReturn([
                ['name' => 'old-world.tar.gz', 'size' => 1234, 'modified' => '2026-10-01T10:00:00Z'],
            ]);
        });

        $this->actingAs($user)->getJson($this->link($server, '/backups/sftp/files'))
            ->assertOk()
            ->assertJsonPath('0.name', 'old-world.tar.gz')
            ->assertJsonPath('0.imported', false);

        $this->actingAs($user)->postJson($this->link($server, '/backups/sftp/import'), ['file' => 'old-world.tar.gz'])
            ->assertOk()
            ->assertJsonPath('attributes.name', 'old-world.tar.gz')
            ->assertJsonPath('attributes.bytes', 1234)
            ->assertJsonPath('attributes.location', 'own_sftp');

        $this->assertDatabaseHas('backups', ['server_id' => $server->id, 'hydra_sftp_target_id' => $target->id, 'hydra_sftp_file' => 'old-world.tar.gz']);

        $this->actingAs($user)->postJson($this->link($server, '/backups/sftp/import'), ['file' => 'old-world.tar.gz'])
            ->assertStatus(400);
        $this->actingAs($user)->postJson($this->link($server, '/backups/sftp/import'), ['file' => 'missing.tar.gz'])
            ->assertStatus(400);

        // The server cannot be removed while backups are stored on it.
        $this->actingAs($user)->deleteJson($this->link($server, '/backups/sftp'))->assertStatus(400);
    }

    public function testBackupOnUnreachableOwnServerCanStillBeDeleted(): void
    {
        config()->set('hydra.addons.sftp_backups.enabled', true);
        [$user, $server] = $this->generateTestAccount();
        $target = $this->ownTarget($server);
        $backup = Backup::factory()->create(['server_id' => $server->id, 'disk' => Backup::ADAPTER_SFTP, 'hydra_sftp_target_id' => $target->id]);

        $this->mock(DaemonSftpRepository::class, function (MockInterface $mock) use ($backup) {
            $mock->expects('setNode->delete')
                ->withArgs(fn (array $target, string $file) => $file === $backup->uuid . '.tar.gz')
                ->andThrow(new SftpBackupException('SFTP: could not connect to the SFTP server'));
        });

        $this->actingAs($user)->deleteJson($this->link($server, '/backups/' . $backup->uuid))->assertNoContent();
        $this->assertSoftDeleted('backups', ['id' => $backup->id]);
    }

    public function testFailureReasonIsStored(): void
    {
        [, $server] = $this->generateTestAccount();
        $backup = Backup::factory()->create(['server_id' => $server->id, 'is_successful' => false, 'completed_at' => null, 'ignored_files' => []]);

        $this->withHeader('Authorization', 'Bearer ' . $server->node->daemon_token_id . '.' . $server->node->getDecryptedKey())
            ->postJson('/api/remote/backups/' . $backup->uuid, ['successful' => false, 'hydra_error' => 'storage_full'])
            ->assertNoContent();

        $this->assertSame('storage_full', $backup->refresh()->hydra_error);
    }

    private function ownTarget(Server $server): SftpBackupTarget
    {
        return SftpBackupTarget::query()->create([
            'server_id' => $server->id,
            'enabled' => true,
            'host' => 'own.example.com',
            'port' => 22,
            'username' => 'user',
            'password' => 'secret',
            'path' => '',
            'host_key' => 'SHA256:own',
        ]);
    }
}
