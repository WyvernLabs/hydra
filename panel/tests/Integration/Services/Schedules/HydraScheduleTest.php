<?php

namespace Pterodactyl\Tests\Integration\Services\Schedules;

use Mockery\MockInterface;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\Task;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Jobs\Schedule\RunTaskJob;
use Pterodactyl\Services\Hydra\Schedules\TaskFlow;
use Pterodactyl\Services\Hydra\Schedules\WebhookSender;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\ServerConfigurationStructureService;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

/**
 * Hydra: schedules started by states and console lines, conditions and the
 * new actions.
 */
class HydraScheduleTest extends ClientApiIntegrationTestCase
{
    /**
     * Creates tasks from [action, payload] pairs.
     *
     * @return Task[]
     */
    private function tasks(Schedule $schedule, array $tasks): array
    {
        $created = [];
        foreach ($tasks as $i => [$action, $payload]) {
            $created[] = Task::factory()->create([
                'schedule_id' => $schedule->id,
                'sequence_id' => $i + 1,
                'action' => $action,
                'payload' => $payload,
            ]);
        }

        return $created;
    }

    private function state(string $state): void
    {
        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) use ($state) {
            $mock->allows('setServer->getDetails')->andReturn(['state' => $state]);
        });
    }

    private function names(Schedule $schedule, int $after = 0): array
    {
        $flow = $this->app->make(TaskFlow::class);
        $names = [];
        while ($task = $flow->next($schedule, $after)) {
            $names[] = $task->payload;
            $after = $task->sequence_id;
        }

        return $names;
    }

    public function testConditionsChooseTheTasksThatRun(): void
    {
        $server = $this->createServerModel();
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);
        $online = json_encode(['check' => 'state', 'op' => 'is', 'value' => 'running']);
        $weekend = json_encode(['check' => 'weekday', 'op' => 'is', 'value' => '6,7']);
        $this->tasks($schedule, [
            ['command', 'before'],
            ['if', $online],
            ['command', 'online'],
            ['if', $weekend],
            ['command', 'online weekend'],
            ['else', ''],
            ['command', 'online weekday'],
            ['endif', ''],
            ['else', ''],
            ['command', 'offline'],
            ['stop', ''],
            ['command', 'never'],
            ['endif', ''],
            ['command', 'after'],
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-24 12:00')); // a Saturday
        $this->state('running');
        $this->assertSame(['before', 'online', 'online weekend', 'after'], $this->names($schedule));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-26 12:00')); // a Monday
        $this->assertSame(['before', 'online', 'online weekday', 'after'], $this->names($schedule));

        // "stop" ends the run inside the else block.
        $this->state('offline');
        $this->assertSame(['before', 'offline'], $this->names($schedule));

        CarbonImmutable::setTestNow();
    }

    public function testTimeRangesMaySpanMidnight(): void
    {
        $at = fn (string $time) => CarbonImmutable::parse("2026-10-24 $time");

        $this->assertTrue(TaskFlow::inTimeRange('08:00-22:00', $at('08:00')));
        $this->assertFalse(TaskFlow::inTimeRange('08:00-22:00', $at('22:00')));
        $this->assertTrue(TaskFlow::inTimeRange('22:00-06:00', $at('23:30')));
        $this->assertTrue(TaskFlow::inTimeRange('22:00-06:00', $at('05:59')));
        $this->assertFalse(TaskFlow::inTimeRange('22:00-06:00', $at('12:00')));
    }

    public function testFirstTaskIsChosenByTheConditions(): void
    {
        Bus::fake();
        $this->state('offline');
        $server = $this->createServerModel();
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);
        $this->tasks($schedule, [
            ['if', json_encode(['check' => 'state', 'op' => 'is', 'value' => 'running'])],
            ['command', 'say online'],
            ['command', 'say offline'],
        ]);
        // Without "else" the block of the "if" ends with the list.
        $this->app->make(\Pterodactyl\Services\Schedules\ProcessScheduleService::class)->handle($schedule);

        Bus::assertNotDispatched(RunTaskJob::class);
        $this->assertFalse($schedule->refresh()->is_processing);
        $this->assertNotNull($schedule->last_run_at);
    }

    public function testStateTriggerIsSentToTheNode(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->sync')->twice();
        });

        $response = $this->actingAs($user)->postJson($this->link($server, '/schedules'), [
            'name' => 'On stop',
            'minute' => '*',
            'hour' => '*',
            'day_of_month' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'is_active' => true,
            'trigger' => 'state',
            'trigger_value' => 'offline',
            'trigger_cooldown' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('attributes.trigger.type', 'state')
            ->assertJsonPath('attributes.trigger.value', 'offline')
            ->assertJsonPath('attributes.next_run_at', null);

        $id = $response->json('attributes.id');
        $config = $this->app->make(ServerConfigurationStructureService::class)->handle($server->refresh());
        $this->assertSame([['schedule' => $id, 'type' => 'state', 'value' => 'offline', 'regex' => false, 'cooldown' => 30]], $config['hydra_triggers']);

        // Invalid states and regular expressions are refused (checked on the update
        // endpoint, creating schedules is rate limited).
        $this->actingAs($user)->postJson($this->link($server, "/schedules/$id"), [
            'name' => 'Bad', 'minute' => '*', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*',
            'is_active' => true, 'trigger' => 'state', 'trigger_value' => 'exploded',
        ])->assertUnprocessable();
        $this->actingAs($user)->postJson($this->link($server, "/schedules/$id"), [
            'name' => 'Bad', 'minute' => '*', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*',
            'is_active' => true, 'trigger' => 'console', 'trigger_value' => '([', 'trigger_regex' => true,
        ])->assertUnprocessable();

        // Deleting the schedule removes the trigger from the node again.
        $this->actingAs($user)->deleteJson($this->link($server, "/schedules/$id"))->assertNoContent();
    }

    public function testNodeStartsTriggeredSchedules(): void
    {
        Bus::fake();
        $server = $this->createServerModel();
        $schedule = Schedule::factory()->create([
            'server_id' => $server->id,
            'hydra_trigger' => 'console',
            'hydra_trigger_value' => 'joined the game',
            'next_run_at' => null,
        ]);
        [$task] = $this->tasks($schedule, [['command', 'say welcome']]);

        $this->trigger($server, $schedule)->assertOk()->assertJsonPath('started', true);
        Bus::assertDispatched(RunTaskJob::class, fn (RunTaskJob $job) => $job->task->id === $task->id);
        $this->assertTrue($schedule->refresh()->is_processing);
        $this->assertNull($schedule->next_run_at);

        // While it runs, it is not started again.
        $this->trigger($server, $schedule)->assertOk()->assertJsonPath('started', false);

        // Time based schedules are not started by the node.
        $cron = Schedule::factory()->create(['server_id' => $server->id]);
        $this->tasks($cron, [['command', 'say hi']]);
        $this->trigger($server, $cron)->assertOk()->assertJsonPath('started', false);

        // Another node cannot start it.
        $other = $this->createServerModel();
        $this->withHeader('Authorization', 'Bearer ' . $other->node->daemon_token_id . '.' . $other->node->getDecryptedKey())
            ->postJson("/api/remote/servers/{$server->uuid}/schedules/{$schedule->id}/trigger")
            ->assertNotFound();
    }

    public function testNewTaskActionsAreValidated(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);
        $link = $this->link($schedule, '/tasks');

        $post = fn (string $action, string $payload) => $this->actingAs($user)->postJson($link, [
            'action' => $action, 'payload' => $payload, 'time_offset' => 5,
        ]);

        $post('wait_state', json_encode(['state' => 'offline', 'timeout' => 120]))->assertOk();
        $post('wait_state', json_encode(['state' => 'gone', 'timeout' => 120]))->assertUnprocessable();
        $post('delete_files', "logs/old.log\ncache")->assertOk();
        $post('delete_files', '/')->assertUnprocessable();
        $post('webhook', json_encode(['url' => 'https://discord.com/api/webhooks/1/abc', 'message' => 'Restart of {server}']))->assertOk();
        $post('webhook', json_encode(['url' => 'http://example.com/hook', 'message' => 'x']))->assertUnprocessable();
        $post('webhook', json_encode(['url' => 'https://127.0.0.1/hook', 'message' => 'x']))->assertUnprocessable();
        $post('if', json_encode(['check' => 'time', 'op' => 'is', 'value' => '22:00-06:00']))->assertOk();
        $post('if', json_encode(['check' => 'time', 'op' => 'is', 'value' => '25:00-06:00']))->assertUnprocessable();
        // Flow control never waits.
        $post('else', '')->assertOk()->assertJsonPath('attributes.time_offset', 0);
        $post('endif', '')->assertOk();
        $post('stop', '')->assertOk();

        // The number of tasks is limited by a setting.
        config()->set('pterodactyl.client_features.schedules.per_schedule_task_limit', 7);
        $post('stop', '')->assertStatus(400);
    }

    public function testWebhookIsPostedWithPlaceholders(): void
    {
        Http::fake(['*' => Http::response('', 204)]);
        $server = $this->createServerModel(['name' => 'Survival']);

        // An address on the internet, so no lookup is needed.
        $this->app->make(WebhookSender::class)->send('https://1.1.1.1/hook', 'Restarting {server}', $server);

        Http::assertSent(fn ($request) => $request->url() === 'https://1.1.1.1/hook' && $request['content'] === 'Restarting Survival');
        $this->assertFalse(WebhookSender::validUrl('https://10.0.0.5/hook'));
        $this->assertFalse(WebhookSender::validUrl('https://user:pass@example.com/hook'));
    }

    private function trigger(Server $server, Schedule $schedule)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $server->node->daemon_token_id . '.' . $server->node->getDecryptedKey())
            ->postJson("/api/remote/servers/{$server->uuid}/schedules/{$schedule->id}/trigger", ['reason' => 'console']);
    }
}
