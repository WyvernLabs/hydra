<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Carbon\CarbonImmutable;
use Mockery\MockInterface;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Hydra\Minecraft\ServerListPing;
use Pterodactyl\Services\Hydra\Minecraft\PlayerStatsService;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: players online on Minecraft servers (addon "player_stats").
 */
class PlayerStatsTest extends ClientApiIntegrationTestCase
{
    public function testStatisticsExistOnlyForTrackedEggs(): void
    {
        [$user, $server] = $this->generateTestAccount();

        config()->set('hydra.addons.player_stats', ['enabled' => true, 'eggs' => [], 'keep_days' => 30]);
        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/players")->assertNotFound();
        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid")->assertJsonPath('attributes.player_stats', false);

        config()->set('hydra.addons.player_stats.eggs', [$server->egg_id]);
        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/players")->assertOk()->assertJsonCount(288, 'points');
        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid")->assertJsonPath('attributes.player_stats', true);
    }

    public function testSamplesAreTakenAndShownAsCourse(): void
    {
        [$user, $server] = $this->generateTestAccount();
        config()->set('hydra.addons.player_stats', ['enabled' => true, 'eggs' => [$server->egg_id], 'keep_days' => 30]);

        $answers = [['online' => 3, 'max' => 20, 'protocol' => 767], ['online' => 7, 'max' => 20, 'protocol' => 767], null, ['online' => 0, 'max' => 0, 'protocol' => -1]];
        // Other servers of the egg from other tests never answer.
        $port = $server->allocation->port;
        $this->mock(ServerListPing::class, function (MockInterface $mock) use (&$answers, $port) {
            $mock->allows('status')->andReturnUsing(function (string $host, int $asked) use (&$answers, $port) {
                return $asked === $port ? array_shift($answers) : null;
            });
        });

        // An old sample is removed by the next round.
        DB::table(PlayerStatsService::TABLE)->insert(['server_id' => $server->id, 'online' => 1, 'max' => 20, 'sampled_at' => CarbonImmutable::now()->subDays(40)]);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->subMinutes(15));
        $this->assertSame(1, app(PlayerStatsService::class)->sample());
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
        $this->assertSame(1, app(PlayerStatsService::class)->sample());
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
        // Nothing answered.
        $this->assertSame(0, app(PlayerStatsService::class)->sample());
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
        // The MOTD addon answered for the stopped server: no players.
        $this->assertSame(0, app(PlayerStatsService::class)->sample());
        CarbonImmutable::setTestNow();

        $this->assertSame(4, DB::table(PlayerStatsService::TABLE)->where('server_id', $server->id)->count());

        $response = $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/players?range=day")
            ->assertOk()
            ->assertJsonPath('peak', 7)
            ->assertJsonPath('average', 5)
            // The latest round found the server offline.
            ->assertJsonPath('current', null);
        $values = array_values(array_filter(array_column($response->json('points'), 'online'), fn ($value) => $value !== null));
        $this->assertSame([3, 7], $values);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/players?range=week")
            ->assertOk()
            ->assertJsonCount(168, 'points')
            ->assertJsonPath('width', 3600);

        DB::table(PlayerStatsService::TABLE)->where('server_id', $server->id)->delete();
    }
}
