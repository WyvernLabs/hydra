<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Schedules;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\Schedule;
use Pterodactyl\Models\Permission;

class StoreScheduleRequest extends ViewScheduleRequest
{
    public function permission(): string
    {
        return Permission::ACTION_SCHEDULE_CREATE;
    }

    public function rules(): array
    {
        $rules = Schedule::getRules();

        return [
            'name' => $rules['name'],
            'is_active' => array_merge(['filled'], $rules['is_active']),
            'minute' => $rules['cron_minute'],
            'hour' => $rules['cron_hour'],
            'day_of_month' => $rules['cron_day_of_month'],
            'day_of_week' => $rules['cron_day_of_week'],
            // Hydra: what starts the schedule.
            'trigger' => 'sometimes|string|in:cron,state,console',
            'trigger_value' => [
                'nullable',
                'string',
                'max:255',
                'required_if:trigger,state,console',
                Rule::when($this->input('trigger') === 'state', [Rule::in(Schedule::TRIGGER_STATES)]),
                function ($attribute, $value, $fail) {
                    if ($this->input('trigger') === 'console' && $this->boolean('trigger_regex') && @preg_match('/' . str_replace('/', '\/', (string) $value) . '/u', '') === false) {
                        $fail(__('server/schedules.invalid_regex'));
                    }
                },
            ],
            'trigger_regex' => 'sometimes|boolean',
            'trigger_cooldown' => 'sometimes|integer|min:5|max:86400',
        ];
    }
}
