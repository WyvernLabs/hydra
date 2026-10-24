<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\Files;

use Mockery\MockInterface;
use Pterodactyl\Models\Permission;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class CompressFilesTest extends ClientApiIntegrationTestCase
{
    public function testEndpointRequiresAuthorization(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_CONTROL_CONSOLE]);

        $this->postJson($this->link($server, '/files/compress'))->assertUnauthorized();

        $this->actingAs($user)
            ->postJson($this->link($server, '/files/compress'))
            ->assertForbidden();
    }

    public function testEndpointTriggersWingsCall(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_ARCHIVE]);

        $this->mock(DaemonFileRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->compressFiles')->with('/', ['test.txt'], 'a1b2c3d4-progress')->andReturn([
                'name' => 'test.zip',
                'mime' => 'application/zip',
            ]);
        });

        $this->actingAs($user)
            ->postJson($endpoint = $this->link($server, '/files/compress'), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta', ['source_field' => 'files', 'rule' => 'required']);

        // Hydra: the progress id is passed on to the daemon, but only in a safe format.
        $this->postJson($endpoint, ['root' => '/', 'files' => ['test.txt'], 'progress' => '../x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'progress');

        $this->postJson($endpoint, ['root' => '/', 'files' => ['test.txt'], 'progress' => 'a1b2c3d4-progress'])
            ->assertOk()
            ->assertJsonPath('object', 'file_object')
            ->assertJsonPath('attributes.name', 'test.zip')
            ->assertJsonPath('attributes.mimetype', 'application/zip');
    }
}
