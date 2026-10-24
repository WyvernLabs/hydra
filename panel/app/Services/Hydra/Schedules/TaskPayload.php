<?php

namespace Pterodactyl\Services\Hydra\Schedules;

use Pterodactyl\Models\Task;
use Pterodactyl\Models\Schedule;

/**
 * Hydra: the actions a task of a schedule can have and the format of their
 * payload. Structured payloads are stored as JSON in the payload column.
 *
 *  - wait_state:   {"state": "offline", "timeout": 300}
 *  - delete_files: one path per line
 *  - webhook:      {"url": "https://…", "message": "…"}
 *  - if:           {"check": "state|file|time|weekday", "op": "is|is_not", "value": "…"}
 *  - else, endif, stop: no payload
 */
class TaskPayload
{
    public const ACTIONS = [
        Task::ACTION_COMMAND, Task::ACTION_POWER, Task::ACTION_BACKUP,
        Task::ACTION_WAIT_STATE, Task::ACTION_DELETE_FILES, Task::ACTION_WEBHOOK,
        Task::ACTION_IF, Task::ACTION_ELSE, Task::ACTION_ENDIF, Task::ACTION_STOP,
    ];

    /**
     * Actions that only steer the flow of the schedule; they run without delay.
     */
    public const CONTROL = [Task::ACTION_IF, Task::ACTION_ELSE, Task::ACTION_ENDIF, Task::ACTION_STOP];

    public const CHECKS = ['state', 'file', 'time', 'weekday'];

    public static function json(?string $payload): array
    {
        $data = json_decode((string) $payload, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Paths of a delete_files task.
     *
     * @return string[]
     */
    public static function paths(?string $payload): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $payload) ?: [])));
    }

    /**
     * Returns why the payload is not valid for the action, or null.
     */
    public static function error(string $action, ?string $payload): ?string
    {
        switch ($action) {
            case Task::ACTION_WAIT_STATE:
                $data = self::json($payload);
                if (!in_array($data['state'] ?? null, Schedule::TRIGGER_STATES, true)) {
                    return __('server/schedules.payload.state');
                }
                $timeout = $data['timeout'] ?? null;
                if (!is_int($timeout) || $timeout < 10 || $timeout > 3600) {
                    return __('server/schedules.payload.timeout');
                }

                return null;
            case Task::ACTION_DELETE_FILES:
                $paths = self::paths($payload);
                if (empty($paths) || count($paths) > 100) {
                    return __('server/schedules.payload.paths');
                }
                foreach ($paths as $path) {
                    if (strlen($path) > 255 || str_contains($path, "\0") || trim($path, '/') === '') {
                        return __('server/schedules.payload.paths');
                    }
                }

                return null;
            case Task::ACTION_WEBHOOK:
                $data = self::json($payload);
                if (!WebhookSender::validUrl((string) ($data['url'] ?? ''))) {
                    return __('server/schedules.payload.url');
                }
                $message = (string) ($data['message'] ?? '');
                if ($message === '' || mb_strlen($message) > 2000) {
                    return __('server/schedules.payload.message');
                }

                return null;
            case Task::ACTION_IF:
                $data = self::json($payload);
                $value = (string) ($data['value'] ?? '');
                if (!in_array($data['check'] ?? null, self::CHECKS, true) || !in_array($data['op'] ?? null, ['is', 'is_not'], true)) {
                    return __('server/schedules.payload.condition');
                }
                $valid = match ($data['check']) {
                    'state' => in_array($value, Schedule::TRIGGER_STATES, true),
                    'file' => $value !== '' && strlen($value) <= 255 && !str_contains($value, "\0") && trim($value, '/') !== '',
                    'time' => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d$/', $value),
                    'weekday' => (bool) preg_match('/^[1-7](,[1-7])*$/', $value),
                };

                return $valid ? null : __('server/schedules.payload.condition');
        }

        return null;
    }
}
