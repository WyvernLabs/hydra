<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Files;

use Mockery\MockInterface;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: subusers limited to certain folders.
 */
class RestrictedFoldersTest extends ClientApiIntegrationTestCase
{
    /**
     * @return array{User, Server}
     */
    private function restrictedAccount(array $paths = ['/plugins/WorldEdit', '/world/build']): array
    {
        [$user, $server] = $this->generateTestAccount([
            Permission::ACTION_FILE_READ,
            Permission::ACTION_FILE_READ_CONTENT,
            Permission::ACTION_FILE_CREATE,
            Permission::ACTION_FILE_UPDATE,
            Permission::ACTION_FILE_DELETE,
            Permission::ACTION_USER_CREATE,
        ]);

        Subuser::query()->where('user_id', $user->id)->update(['allowed_paths' => json_encode($paths)]);

        return [$user, $server->refresh()];
    }

    public function testFilesOutsideOfTheFoldersCannotBeRead(): void
    {
        [$user, $server] = $this->restrictedAccount();

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->getContent')->with('/plugins/WorldEdit/config.yml', config('pterodactyl.files.max_edit_size'))->andReturn('ok');
        });

        $this->actingAs($user)->getJson($this->link($server, '/files/contents?file=/server.properties'))->assertForbidden();
        $this->actingAs($user)->getJson($this->link($server, '/files/contents?file=/world/build/../../server.properties'))->assertForbidden();
        $this->actingAs($user)->getJson($this->link($server, '/files/contents?file=/plugins/WorldEdit/config.yml'))->assertOk();
    }

    public function testListingOnlyShowsTheWayToTheFolders(): void
    {
        [$user, $server] = $this->restrictedAccount();

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->getDirectory')->with('/')->andReturn([
                ['name' => 'plugins', 'file' => false],
                ['name' => 'world', 'file' => false],
                ['name' => 'server.properties', 'file' => true],
            ]);
        });

        $response = $this->actingAs($user)->getJson($this->link($server, '/files/list?directory=/'))->assertOk();

        $this->assertSame(['plugins', 'world'], array_column(array_column($response->json('data'), 'attributes'), 'name'));

        $this->actingAs($user)->getJson($this->link($server, '/files/list?directory=/config'))->assertForbidden();
    }

    public function testFoldersAboveTheAllowedOnesCannotBeChanged(): void
    {
        [$user, $server] = $this->restrictedAccount();

        $this->actingAs($user)
            ->postJson($this->link($server, '/files/delete'), ['root' => '/plugins', 'files' => ['WorldEdit']])
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson($this->link($server, '/files/rename'), ['root' => '/plugins/WorldEdit', 'files' => [['from' => 'config.yml', 'to' => '../../server.properties']]])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson($this->link($server, '/files/create-folder'), ['root' => '/', 'name' => 'new'])
            ->assertForbidden();
    }

    public function testRestrictedSubuserCanOnlyHandOutTheirOwnFolders(): void
    {
        [$user, $server] = $this->restrictedAccount(['/plugins']);

        $this->actingAs($user)->postJson($this->link($server, '/users'), [
            'email' => 'builder@example.com',
            'permissions' => [Permission::ACTION_FILE_READ],
        ])->assertForbidden();

        $this->actingAs($user)->postJson($this->link($server, '/users'), [
            'email' => 'builder@example.com',
            'permissions' => [Permission::ACTION_FILE_READ],
            'allowed_paths' => ['/world'],
        ])->assertForbidden();

        $this->actingAs($user)->postJson($this->link($server, '/users'), [
            'email' => 'builder@example.com',
            'permissions' => [Permission::ACTION_FILE_READ],
            'allowed_paths' => ['plugins/WorldEdit/'],
        ])->assertOk()->assertJsonPath('attributes.allowed_paths', ['/plugins/WorldEdit']);
    }

    public function testOwnerIsNeverRestricted(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->getContent')->andReturn('ok');
        });

        $this->actingAs($user)->getJson($this->link($server, '/files/contents?file=/server.properties'))->assertOk();
    }
}
