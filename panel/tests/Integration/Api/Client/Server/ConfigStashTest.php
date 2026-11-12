<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraConfigStash;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: storage of configs for the config sync (addon "config_sync").
 */
class ConfigStashTest extends ClientApiIntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config()->set('hydra.addons.config_sync.enabled', true);
        config()->set('hydra.addons.config_sync.max_entries', 2);
        config()->set('hydra.addons.config_sync.max_size', 16);
    }

    public function testRoutesOnlyExistWhileTheAddonIsOn(): void
    {
        [$user, $server] = $this->generateTestAccount();
        config()->set('hydra.addons.config_sync.enabled', false);

        $this->actingAs($user)->getJson($this->link($server, '/config-stash'))->assertNotFound();
    }

    public function testStoreListShowAndDelete(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/config-stash'), [
            'path' => 'plugins/Shop/config.yml',
            'content' => "prefix: '&6[Shop]'\n",
        ])
            ->assertOk()
            ->assertJsonPath('name', 'config.yml')
            ->assertJsonPath('path', '/plugins/Shop/config.yml')
            ->assertJsonPath('created_by', $user->username);

        /** @var HydraConfigStash $entry */
        $entry = HydraConfigStash::query()->where('server_id', $server->id)->firstOrFail();
        $this->assertSame(19, $entry->size);

        $this->actingAs($user)->getJson($this->link($server, '/config-stash'))
            ->assertOk()
            ->assertJsonPath('entries.0.id', $entry->id)
            ->assertJsonMissingPath('entries.0.content')
            ->assertJsonPath('max_entries', 2)
            ->assertJsonPath('max_size', 16);

        $this->actingAs($user)->getJson($this->link($server, "/config-stash/$entry->id"))
            ->assertOk()
            ->assertJsonPath('content', "prefix: '&6[Shop]'\n");

        $this->actingAs($user)->deleteJson($this->link($server, "/config-stash/$entry->id"))->assertNoContent();
        $this->assertFalse(HydraConfigStash::query()->whereKey($entry->id)->exists());
    }

    public function testLimitsAreEnforced(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->actingAs($user)->postJson($this->link($server, '/config-stash'), ['path' => '/a.yml', 'content' => str_repeat('a', 16 * 1024 + 1)])
            ->assertUnprocessable();

        foreach (['/a.yml', '/b.yml'] as $path) {
            $this->actingAs($user)->postJson($this->link($server, '/config-stash'), ['path' => $path, 'content' => 'a: 1'])->assertOk();
        }
        $this->actingAs($user)->postJson($this->link($server, '/config-stash'), ['path' => '/c.yml', 'content' => 'a: 1'])
            ->assertUnprocessable();
    }

    public function testEntriesOfOtherServersAreHidden(): void
    {
        [$user, $server] = $this->generateTestAccount();
        [, $other] = $this->generateTestAccount();
        $foreign = HydraConfigStash::query()->create([
            'server_id' => $other->id, 'name' => 'x', 'path' => '/x.yml', 'content' => 'secret: 1', 'size' => 9,
        ]);

        $this->actingAs($user)->getJson($this->link($server, "/config-stash/$foreign->id"))->assertNotFound();
        $this->actingAs($user)->deleteJson($this->link($server, "/config-stash/$foreign->id"))->assertNotFound();
        $this->actingAs($user)->getJson($this->link($server, '/config-stash'))->assertOk()->assertJsonCount(0, 'entries');
    }

    public function testSubusersNeedThePermission(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ, Permission::ACTION_FILE_READ_CONTENT, Permission::ACTION_FILE_UPDATE]);
        $this->actingAs($user)->getJson($this->link($server, '/config-stash'))->assertForbidden();
        $this->actingAs($user)->postJson($this->link($server, '/config-stash'), ['path' => '/a.yml', 'content' => 'a: 1'])->assertForbidden();

        [$allowed, $server] = $this->generateTestAccount([Permission::ACTION_FILE_CONFIG_SYNC]);
        $this->actingAs($allowed)->getJson($this->link($server, '/config-stash'))->assertOk();
    }
}
