<?php

namespace Pterodactyl\Services\Hydra\Schedules;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Task;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Illuminate\Support\Collection;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: the order in which the tasks of a schedule run. "if" tasks check a
 * condition and continue in their block, or after the matching "else" or
 * "endif"; reaching an "else" from its "if" block skips to the "endif";
 * "stop" ends the run. These control tasks never run as jobs themselves.
 */
class TaskFlow
{
    public function __construct(
        private DaemonServerRepository $serverRepository,
        private DaemonFileRepository $fileRepository,
    ) {
    }

    /**
     * The next task that does something, after the task with the given
     * sequence id (0 for the start of the schedule), or null when the run ends.
     */
    public function next(Schedule $schedule, int $afterSequence): ?Task
    {
        /** @var Collection<int, Task> $tasks */
        $tasks = $schedule->tasks()->orderBy('sequence_id')->get()->values();
        $count = $tasks->count();

        $i = $tasks->search(fn (Task $task) => $task->sequence_id > $afterSequence);
        if ($i === false) {
            return null;
        }

        while ($i < $count) {
            $task = $tasks[$i];
            switch ($task->action) {
                case Task::ACTION_ENDIF:
                    $i++;
                    break;
                case Task::ACTION_ELSE:
                    // Reached from the end of the "if" block: skip the "else" block.
                    $i = self::matching($tasks, $i, false) + 1;
                    break;
                case Task::ACTION_IF:
                    $i = $this->check($task, $schedule->server) ? $i + 1 : self::matching($tasks, $i, true) + 1;
                    break;
                case Task::ACTION_STOP:
                    return null;
                default:
                    return $task;
            }
        }

        return null;
    }

    /**
     * Index of the "endif" (or, with $else, the "else") belonging to the
     * block that starts at $start. Without one, the end of the list.
     *
     * @param Collection<int, Task> $tasks
     */
    public static function matching(Collection $tasks, int $start, bool $else): int
    {
        $depth = 0;
        for ($j = $start + 1; $j < $tasks->count(); ++$j) {
            $action = $tasks[$j]->action;
            if ($action === Task::ACTION_IF) {
                ++$depth;
            } elseif ($action === Task::ACTION_ENDIF) {
                if ($depth === 0) {
                    return $j;
                }
                --$depth;
            } elseif ($action === Task::ACTION_ELSE && $else && $depth === 0) {
                return $j;
            }
        }

        return $tasks->count();
    }

    /**
     * Whether the condition of an "if" task holds. A condition that cannot be
     * checked (e.g. the node is offline) does not hold.
     */
    public function check(Task $task, Server $server): bool
    {
        $data = TaskPayload::json($task->payload);
        $value = (string) ($data['value'] ?? '');

        try {
            $result = match ($data['check'] ?? null) {
                'state' => ($this->serverRepository->setServer($server)->getDetails()['state'] ?? 'offline') === $value,
                'file' => $this->fileExists($server, $value),
                'time' => self::inTimeRange($value, CarbonImmutable::now()),
                'weekday' => in_array((string) CarbonImmutable::now()->dayOfWeekIso, explode(',', $value), true),
                default => null,
            };
        } catch (\Throwable) {
            return false;
        }

        if (is_null($result)) {
            return false;
        }

        return ($data['op'] ?? 'is') === 'is_not' ? !$result : $result;
    }

    /**
     * Whether the time is within "HH:MM-HH:MM", which may span midnight.
     */
    public static function inTimeRange(string $range, CarbonImmutable $now): bool
    {
        [$from, $to] = array_pad(explode('-', $range, 2), 2, '');
        $current = $now->format('H:i');

        return $from <= $to ? ($current >= $from && $current < $to) : ($current >= $from || $current < $to);
    }

    private function fileExists(Server $server, string $path): bool
    {
        $path = '/' . trim($path, '/');
        $directory = dirname($path);
        $name = basename($path);

        try {
            $entries = $this->fileRepository->setServer($server)->getDirectory($directory);
        } catch (\Throwable) {
            // A missing folder holds no file.
            return false;
        }
        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
}
