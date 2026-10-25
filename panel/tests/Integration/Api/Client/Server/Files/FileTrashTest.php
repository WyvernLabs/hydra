<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Files;

use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Mockery\MockInterface;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\Permission;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonTrashRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: trash and previews of the file manager.
 */
class FileTrashTest extends ClientApiIntegrationTestCase
{
    private const ENTRIES = [
        ['id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'path' => '/plugins/WorldEdit/config.yml', 'directory' => false, 'size' => 10, 'deleted_at' => '2026-10-24T10:00:00Z', 'expires_at' => '2026-10-31T10:00:00Z'],
        ['id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'path' => '/server.properties', 'directory' => false, 'size' => 20, 'deleted_at' => '2026-10-24T09:00:00Z', 'expires_at' => '2026-10-31T09:00:00Z'],
    ];

    private array $calls = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->calls = [];
        $this->mock(DaemonTrashRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('list')->andReturn(self::ENTRIES);
            $mock->allows('restore')->andReturnUsing(function (array $ids) {
                $this->calls[] = ['restore', $ids];

                return array_fill_keys($ids, '/restored');
            });
            $mock->allows('delete')->andReturnUsing(fn (array $ids) => $this->calls[] = ['delete', $ids]);
            $mock->allows('emptyTrash')->andReturnUsing(fn () => $this->calls[] = ['empty']);
        });
    }

    public function testDeletedFilesGoIntoTheTrash(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_DELETE]);
        config()->set('hydra.addons.trash.enabled', true);
        config()->set('hydra.addons.trash.days', 3);

        $this->mockDeletions();

        $this->actingAs($user)->postJson($this->link($server, '/files/delete'), ['root' => '/', 'files' => ['a.txt']])->assertNoContent();
        // Deleting for good is only possible from the trash, the old flag is ignored.
        $this->actingAs($user)->postJson($this->link($server, '/files/delete'), ['root' => '/', 'files' => ['b.txt'], 'permanent' => true])->assertNoContent();

        $this->assertSame([['/', ['a.txt'], 3 * 86400], ['/', ['b.txt'], 3 * 86400]], $this->calls);
    }

    private function mockDeletions(): void
    {
        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('deleteFiles')->andReturnUsing(function (...$args) {
                $this->calls[] = $args;

                return new \GuzzleHttp\Psr7\Response(204);
            });
        });
    }

    public function testFilesAreDeletedForGoodWhileTheTrashIsOff(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_DELETE]);
        config()->set('hydra.addons.trash.enabled', false);

        $this->mockDeletions();

        $this->actingAs($user)->postJson($this->link($server, '/files/delete'), ['root' => '/', 'files' => ['a.txt']])->assertNoContent();

        $this->assertSame([['/', ['a.txt'], null]], $this->calls);
    }

    public function testTrashNeedsPermissions(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);

        $this->actingAs($user)->getJson($this->link($server, '/files/trash'))->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($user)->postJson($this->link($server, '/files/trash/restore'), ['ids' => [self::ENTRIES[0]['id']]])->assertForbidden();
        $this->actingAs($user)->postJson($this->link($server, '/files/trash/delete'), ['all' => true])->assertForbidden();
        $this->assertSame([], $this->calls);
    }

    public function testDeletingFromTheTrashNeedsItsOwnPermission(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ, Permission::ACTION_FILE_DELETE]);

        $this->actingAs($user)->postJson($this->link($server, '/files/trash/delete'), ['ids' => [self::ENTRIES[0]['id']]])->assertForbidden();
        $this->actingAs($user)->postJson($this->link($server, '/files/trash/delete'), ['all' => true])->assertForbidden();
        $this->assertSame([], $this->calls);
    }

    public function testDeletingFromTheTrashWithPermission(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ, Permission::ACTION_FILE_DELETE_PERMANENT]);

        $this->actingAs($user)->postJson($this->link($server, '/files/trash/delete'), ['ids' => [self::ENTRIES[0]['id']]])->assertNoContent();

        $this->assertSame([['delete', [self::ENTRIES[0]['id']]]], $this->calls);
    }

    public function testOwnerRestoresAndEmpties(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->getJson($this->link($server, '/files/trash'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'config.yml')
            ->assertJsonPath('meta.days', 7);

        $this->actingAs($user)->postJson($this->link($server, '/files/trash/restore'), ['ids' => ['../../etc']])->assertUnprocessable();
        $this->actingAs($user)->postJson($this->link($server, '/files/trash/restore'), ['ids' => [str_repeat('c', 32)]])->assertStatus(400);
        $this->actingAs($user)->postJson($this->link($server, '/files/trash/restore'), ['ids' => [self::ENTRIES[1]['id']]])
            ->assertOk()
            ->assertJsonPath('restored.' . self::ENTRIES[1]['id'], '/restored');
        $this->actingAs($user)->postJson($this->link($server, '/files/trash/delete'), ['all' => true])->assertNoContent();

        $this->assertSame([['restore', [self::ENTRIES[1]['id']]], ['empty']], $this->calls);
    }

    public function testRestrictedSubusersOnlySeeTheirFolders(): void
    {
        [$user, $server] = $this->generateTestAccount([
            Permission::ACTION_FILE_READ,
            Permission::ACTION_FILE_CREATE,
            Permission::ACTION_FILE_DELETE_PERMANENT,
        ]);
        Subuser::query()->where('user_id', $user->id)->update(['allowed_paths' => json_encode(['/plugins/WorldEdit'])]);

        $this->actingAs($user)->getJson($this->link($server, '/files/trash'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::ENTRIES[0]['id']);

        $this->actingAs($user)->postJson($this->link($server, '/files/trash/restore'), ['ids' => [self::ENTRIES[1]['id']]])->assertStatus(400);
        // Emptying only removes what the subuser may see.
        $this->actingAs($user)->postJson($this->link($server, '/files/trash/delete'), ['all' => true])->assertNoContent();

        $this->assertSame([['delete', [self::ENTRIES[0]['id']]]], $this->calls);
    }

    public function testPreviewLinksCanBeReused(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ_CONTENT]);

        $url = $this->actingAs($user)->getJson($this->link($server, '/files/preview?file=/video.mp4'))
            ->assertOk()
            ->json('attributes.url');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = (new Parser(new JoseEncoder()))->parse($query['token']);

        $this->assertTrue($token->claims()->get('hydra_preview'));
        $this->assertSame('/video.mp4', $token->claims()->get('file_path'));
        $this->assertSame($server->uuid, $token->claims()->get('server_uuid'));
    }
}
