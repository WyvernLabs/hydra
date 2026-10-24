<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\Egg;
use Mockery\MockInterface;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Models\HydraGameProfile;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonProfileRepository;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: game library (addon "games").
 */
class GameLibraryTest extends ClientApiIntegrationTestCase
{
    private array $actions = [];

    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.games.enabled', true);
        config()->set('hydra.addons.games.eggs', []);
        config()->set('hydra.addons.games.max_profiles', 1);

        $this->actions = [];
        $this->mock(DaemonProfileRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('save')->andReturnUsing(function (string $id) {
                $this->actions[] = "save:$id";

                return 1234;
            });
            $mock->allows('load')->andReturnUsing(fn (string $id) => $this->actions[] = "load:$id");
            $mock->allows('wipe')->andReturnUsing(fn () => $this->actions[] = 'wipe');
            $mock->allows('delete')->andReturnUsing(fn (string $id) => $this->actions[] = "delete:$id");
        });
        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->allows('setServer')->andReturnSelf();
            $mock->allows('getDetails')->andReturn(['state' => 'offline']);
            $mock->allows('reinstall')->andReturnUsing(fn () => $this->actions[] = 'reinstall');
            $mock->allows('sync')->andReturnUsing(fn () => $this->actions[] = 'sync');
        });
    }

    /**
     * Another egg in the nest of the server, with one variable.
     */
    private function otherEgg(Server $server): Egg
    {
        /** @var Egg $egg */
        $egg = Egg::query()->where('nest_id', $server->nest_id)->where('id', '!=', $server->egg_id)->firstOrFail();
        $egg->forceFill(['startup' => 'java -jar other.jar', 'docker_images' => ['Java' => 'ghcr.io/test/other:latest']])->save();

        return $egg;
    }

    public function testSwitchKeepsTheOldGameAsProfile(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $oldEgg = $server->egg;
        $server->forceFill(['startup' => 'my custom start'])->save();
        $egg = $this->otherEgg($server);

        $this->actingAs($user)->getJson($this->link($server, '/games'))
            ->assertOk()
            ->assertJsonPath('current', $oldEgg->id);

        $this->actingAs($user)->postJson($this->link($server, '/games/switch'), ['egg' => $egg->id, 'keep' => true, 'name' => 'My world'])
            ->assertNoContent();

        $server->refresh();
        $this->assertSame($egg->id, $server->egg_id);
        $this->assertSame('java -jar other.jar', $server->startup);
        $this->assertSame('ghcr.io/test/other:latest', $server->image);
        $this->assertSame(Server::STATUS_INSTALLING, $server->status);
        $this->assertSame(
            $egg->variables()->count(),
            ServerVariable::query()->where('server_id', $server->id)->count()
        );

        /** @var HydraGameProfile $profile */
        $profile = $server->gameProfiles()->firstOrFail();
        $this->assertSame('My world', $profile->name);
        $this->assertSame($oldEgg->id, $profile->egg_id);
        $this->assertSame('my custom start', $profile->startup);
        $this->assertSame(1234, $profile->size);
        $this->assertSame(["save:$profile->uuid", 'reinstall'], $this->actions);
    }

    public function testProfileIsLoadedBack(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $oldEgg = $server->egg;
        /** @var EggVariable $variable */
        $variable = EggVariable::factory()->create(['egg_id' => $oldEgg->id, 'env_variable' => 'HYDRA_TEST', 'default_value' => 'x']);
        $profile = HydraGameProfile::query()->create([
            'server_id' => $server->id,
            'uuid' => 'profile-1234-abcd',
            'name' => 'Old world',
            'egg_id' => $oldEgg->id,
            'startup' => 'old start',
            'image' => 'ghcr.io/test/old:1',
            'variables' => ['HYDRA_TEST' => 'kept value'],
        ]);
        $egg = $this->otherEgg($server);
        $server->forceFill(['egg_id' => $egg->id, 'nest_id' => $egg->nest_id])->save();
        $server->status = null;
        $server->save();

        // Limit of one profile: loading while keeping the current game would need a second.
        $this->actingAs($user)->postJson($this->link($server, "/games/profiles/$profile->id/load"), ['keep' => true])
            ->assertStatus(400);

        $this->actingAs($user)->postJson($this->link($server, "/games/profiles/$profile->id/load"), ['keep' => false])
            ->assertNoContent();

        $server->refresh();
        $this->assertSame($oldEgg->id, $server->egg_id);
        $this->assertSame('old start', $server->startup);
        $this->assertSame('ghcr.io/test/old:1', $server->image);
        $this->assertNull($server->status);
        $this->assertSame('kept value', ServerVariable::query()->where('server_id', $server->id)->where('variable_id', $variable->id)->value('variable_value'));
        $this->assertModelMissing($profile);
        $this->assertSame(['wipe', 'load:profile-1234-abcd', 'sync'], $this->actions);

        $variable->delete();
    }

    public function testProfilesCanBeDeleted(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $profile = HydraGameProfile::query()->create([
            'server_id' => $server->id, 'uuid' => 'profile-delete-me', 'name' => 'Old', 'egg_id' => $server->egg_id,
            'startup' => '', 'image' => 'x', 'variables' => [],
        ]);

        $this->actingAs($user)->deleteJson($this->link($server, "/games/profiles/$profile->id"))->assertNoContent();
        $this->assertModelMissing($profile);
        $this->assertSame(['delete:profile-delete-me'], $this->actions);
    }

    public function testOnlyOfferedEggsAndPermittedUsers(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_SETTINGS_RENAME]);
        $egg = $this->otherEgg($server);
        $this->actingAs($user)->getJson($this->link($server, '/games'))->assertForbidden();

        [$owner, $server] = $this->generateTestAccount();
        $egg = $this->otherEgg($server);
        config()->set('hydra.addons.games.eggs', [$server->egg_id]);
        $this->actingAs($owner)->postJson($this->link($server, '/games/switch'), ['egg' => $egg->id, 'keep' => false])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'This game is not offered.');
        $this->assertSame([], $this->actions);

        config()->set('hydra.addons.games.enabled', false);
        $this->actingAs($owner)->getJson($this->link($server, '/games'))->assertNotFound();
    }
}
