<?php

namespace Pterodactyl\Jobs\Schedule;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Pterodactyl\Services\Hydra\Schedules\TaskFlow;
use Pterodactyl\Services\Backups\InitiateBackupService;
use Pterodactyl\Services\Hydra\Schedules\TaskPayload;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Hydra\Schedules\WebhookSender;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;

class RunTaskJob implements ShouldQueue
{
    use Queueable;
    use DispatchesJobs;
    use SerializesModels;

    /**
     * Hydra: how often a "wait for state" task checks the server again, in seconds.
     */
    public const WAIT_INTERVAL = 5;

    /**
     * RunTaskJob constructor.
     *
     * @param int|null $waitingSince Hydra: when a "wait for state" task started waiting (unix time)
     */
    public function __construct(public Task $task, public bool $manualRun = false, public ?int $waitingSince = null)
    {
        $this->queue = 'standard';
    }

    /**
     * Run the job and send actions to the daemon running the server.
     *
     * @throws \Throwable
     */
    public function handle(
        DaemonCommandRepository $commandRepository,
        InitiateBackupService $backupService,
        DaemonPowerRepository $powerRepository,
        DaemonServerRepository $serverRepository,
        DaemonFileRepository $fileRepository,
        WebhookSender $webhook,
        TaskFlow $flow,
    ) {
        // Do not process a task that is not set to active, unless it's been manually triggered.
        if (!$this->task->schedule->is_active && !$this->manualRun) {
            $this->markTaskNotQueued();
            $this->markScheduleComplete();

            return;
        }

        $server = $this->task->server;
        // If we made it to this point and the server status is not null it means the
        // server was likely suspended or marked as reinstalling after the schedule
        // was queued up. Just end the task right now — this should be a very rare
        // condition.
        if (!is_null($server->status)) {
            $this->failed();

            return;
        }

        // Perform the provided task against the daemon.
        try {
            switch ($this->task->action) {
                case Task::ACTION_POWER:
                    $powerRepository->setServer($server)->send($this->task->payload);
                    break;
                case Task::ACTION_COMMAND:
                    $commandRepository->setServer($server)->send($this->task->payload);
                    break;
                case Task::ACTION_BACKUP:
                    $backupService->setIgnoredFiles(explode(PHP_EOL, $this->task->payload))->handle($server, null, true);
                    break;
                case Task::ACTION_WAIT_STATE:
                    if (!$this->waitForState($serverRepository)) {
                        return;
                    }
                    break;
                case Task::ACTION_DELETE_FILES:
                    $fileRepository->setServer($server)->deleteFiles('/', TaskPayload::paths($this->task->payload));
                    break;
                case Task::ACTION_WEBHOOK:
                    $data = TaskPayload::json($this->task->payload);
                    $webhook->send((string) ($data['url'] ?? ''), (string) ($data['message'] ?? ''), $server);
                    break;
                case Task::ACTION_IF:
                case Task::ACTION_ELSE:
                case Task::ACTION_ENDIF:
                case Task::ACTION_STOP:
                    // Flow control is resolved when looking for the next task.
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid task action provided: ' . $this->task->action);
            }
        } catch (\Exception $exception) {
            // Hydra: a task that may fail lets the schedule continue whatever went wrong.
            if (!$this->task->continue_on_failure || $exception instanceof \InvalidArgumentException) {
                throw $exception;
            }
        }

        $this->markTaskNotQueued();
        $this->queueNextTask($flow);
    }

    /**
     * Handle a failure while sending the action to the daemon or otherwise processing the job.
     */
    public function failed(?\Exception $exception = null)
    {
        $this->markTaskNotQueued();
        $this->markScheduleComplete();
    }

    /**
     * Hydra: true once the server is in the state the task waits for. Until
     * then the task checks again every few seconds, up to its timeout.
     *
     * @throws DisplayException
     */
    private function waitForState(DaemonServerRepository $serverRepository): bool
    {
        $data = TaskPayload::json($this->task->payload);
        try {
            $state = $serverRepository->setServer($this->task->server)->getDetails()['state'] ?? null;
        } catch (\Exception) {
            $state = null;
        }
        if ($state === ($data['state'] ?? null)) {
            return true;
        }

        $since = $this->waitingSince ?? CarbonImmutable::now()->getTimestamp();
        if (CarbonImmutable::now()->getTimestamp() - $since >= (int) ($data['timeout'] ?? 60)) {
            throw new DisplayException(__('server/schedules.wait_timeout'));
        }

        $this->dispatch((new self($this->task, $this->manualRun, $since))->delay(self::WAIT_INTERVAL));

        return false;
    }

    /**
     * Get the next task in the schedule and queue it for running after the defined period of wait time.
     */
    private function queueNextTask(TaskFlow $flow)
    {
        $nextTask = $flow->next($this->task->schedule, $this->task->sequence_id);

        if (is_null($nextTask)) {
            $this->markScheduleComplete();

            return;
        }

        $nextTask->update(['is_queued' => true]);

        $this->dispatch((new self($nextTask, $this->manualRun))->delay($nextTask->time_offset));
    }

    /**
     * Marks the parent schedule as being complete.
     */
    private function markScheduleComplete()
    {
        $this->task->schedule()->update([
            'is_processing' => false,
            'last_run_at' => CarbonImmutable::now()->toDateTimeString(),
        ]);
    }

    /**
     * Mark a specific task as no longer being queued.
     */
    private function markTaskNotQueued()
    {
        $this->task->update(['is_queued' => false]);
    }
}
