<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Pterodactyl\Http\Controllers\Api\Client\Servers\ActivityLogController;

/**
 * Hydra: the activity log of a server can be filtered by user, action and text.
 */
class ActivityFilterTest extends ClientApiIntegrationTestCase
{
    public function testLogIsFilteredByUserActionAndText(): void
    {
        [$owner, $server] = $this->generateTestAccount();
        $helper = User::factory()->create();
        Subuser::query()->create(['user_id' => $helper->id, 'server_id' => $server->id, 'permissions' => [Permission::ACTION_WEBSOCKET_CONNECT]]);

        Activity::event('server:file.write')->subject($server)->actor($owner)->property('file', '/server.properties')->log();
        Activity::event('server:file.delete')->subject($server)->actor($helper)->property('files', ['/world/old.zip'])->log();
        Activity::event('server:file.write')->subject($server)->actor($helper)->property('file', '/plugins/config.yml')->log();
        Activity::reset();

        $url = "/api/client/servers/$server->uuid/activity";
        $events = fn (array $query) => array_column(array_column($this->actingAs($owner)->getJson($url . '?' . http_build_query($query))->assertOk()->json('data'), 'attributes'), 'event');

        $this->assertCount(3, $events([]));
        $this->assertSame(['server:file.delete', 'server:file.write'], array_values(array_unique($events(['filter' => ['actor' => $helper->id]]))));
        $this->assertCount(2, $events(['filter' => ['actor' => $helper->id]]));
        $this->assertSame(['server:file.write', 'server:file.write'], $events(['filter' => ['action' => 'server:file.write']]));
        $this->assertSame(['server:file.write'], $events(['filter' => ['action' => 'server:file.write', 'actor' => $owner->id]]));
        $this->assertSame(['server:file.delete'], $events(['filter' => ['search' => 'old.zip']]));
        // Wildcards of the database are taken literally.
        $this->assertSame([], $events(['filter' => ['search' => '%']]));

        $options = $this->actingAs($owner)->getJson("$url/filters")->assertOk()->json();
        $this->assertEqualsCanonicalizing([$owner->username, $helper->username], array_column($options['actors'], 'username'));
        $this->assertEqualsCanonicalizing(['server:file.delete', 'server:file.write'], array_column($options['events'], 'event'));
        foreach ($options['events'] as $item) {
            $this->assertNotSame($item['event'], $item['label']);
            $this->assertStringNotContainsString(':file', $item['label']);
        }
    }

    public function testFiltersNeedTheActivityPermission(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_FILE_READ]);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/activity/filters")->assertForbidden();
    }

    public function testLabelsLeaveOutPlaceholders(): void
    {
        app()->setLocale('en');
        $this->assertSame('unknown:event', ActivityLogController::label('unknown:event'));
        $this->assertStringNotContainsString(':', ActivityLogController::label('server:file.write'));
    }
}
