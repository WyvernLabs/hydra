<?php

namespace Pterodactyl\Models;

use Cron\CronExpression;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Pterodactyl\Contracts\Extensions\HashidsInterface;

/**
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string $cron_day_of_week
 * @property string $cron_month
 * @property string $cron_day_of_month
 * @property string $cron_hour
 * @property string $cron_minute
 * @property bool $is_active
 * @property bool $is_processing
 * @property bool $only_when_online
 * @property string $hydra_trigger
 * @property string|null $hydra_trigger_value
 * @property bool $hydra_trigger_regex
 * @property int $hydra_trigger_cooldown
 * @property \Carbon\Carbon|null $last_run_at
 * @property \Carbon\Carbon|null $next_run_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string $hashid
 * @property Server $server
 * @property \Illuminate\Database\Eloquent\Collection<int, \Pterodactyl\Models\Task> $tasks
 */
class Schedule extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleFactory> */
    use HasFactory;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'server_schedule';

    /**
     * Hydra: what starts a schedule. "cron" runs it at the times of the cron
     * fields, "state" when the server reaches a state, "console" when a line in
     * its console matches.
     */
    public const TRIGGER_CRON = 'cron';
    public const TRIGGER_STATE = 'state';
    public const TRIGGER_CONSOLE = 'console';
    public const TRIGGERS = [self::TRIGGER_CRON, self::TRIGGER_STATE, self::TRIGGER_CONSOLE];
    public const TRIGGER_STATES = ['running', 'offline', 'starting', 'stopping'];

    /**
     * The table associated with the model.
     */
    protected $table = 'schedules';

    /**
     * Always return the tasks associated with this schedule.
     */
    protected $with = ['tasks'];

    /**
     * Mass assignable attributes on this model.
     */
    protected $fillable = [
        'server_id',
        'name',
        'cron_day_of_week',
        'cron_month',
        'cron_day_of_month',
        'cron_hour',
        'cron_minute',
        'is_active',
        'is_processing',
        'only_when_online',
        'last_run_at',
        'next_run_at',
        'hydra_trigger',
        'hydra_trigger_value',
        'hydra_trigger_regex',
        'hydra_trigger_cooldown',
    ];

    protected $casts = [
        'id' => 'integer',
        'server_id' => 'integer',
        'is_active' => 'boolean',
        'is_processing' => 'boolean',
        'only_when_online' => 'boolean',
        'hydra_trigger_regex' => 'boolean',
        'hydra_trigger_cooldown' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    protected $attributes = [
        'name' => null,
        'cron_day_of_week' => '*',
        'cron_month' => '*',
        'cron_day_of_month' => '*',
        'cron_hour' => '*',
        'cron_minute' => '*',
        'is_active' => true,
        'is_processing' => false,
        'only_when_online' => false,
        'hydra_trigger' => self::TRIGGER_CRON,
        'hydra_trigger_regex' => false,
        'hydra_trigger_cooldown' => 60,
    ];

    public static array $validationRules = [
        'server_id' => 'required|exists:servers,id',
        'name' => 'required|string|max:191',
        'cron_day_of_week' => 'required|string',
        'cron_month' => 'required|string',
        'cron_day_of_month' => 'required|string',
        'cron_hour' => 'required|string',
        'cron_minute' => 'required|string',
        'is_active' => 'boolean',
        'is_processing' => 'boolean',
        'only_when_online' => 'boolean',
        'last_run_at' => 'nullable|date',
        'next_run_at' => 'nullable|date',
        'hydra_trigger' => 'string|in:cron,state,console',
        'hydra_trigger_value' => 'nullable|string|max:255',
        'hydra_trigger_regex' => 'boolean',
        'hydra_trigger_cooldown' => 'integer|min:5|max:86400',
    ];

    /**
     * Hydra: the schedule runs at the times of its cron fields.
     */
    public function isCron(): bool
    {
        return ($this->hydra_trigger ?: self::TRIGGER_CRON) === self::TRIGGER_CRON;
    }

    /**
     * Hydra: the trigger as the node expects it, null for time based schedules.
     */
    public function toTrigger(): ?array
    {
        if ($this->isCron() || !$this->is_active || empty($this->hydra_trigger_value)) {
            return null;
        }

        return [
            'schedule' => $this->id,
            'type' => $this->hydra_trigger,
            'value' => $this->hydra_trigger_value,
            'regex' => $this->hydra_trigger_regex,
            'cooldown' => $this->hydra_trigger_cooldown,
        ];
    }

    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Returns the schedule's execution crontab entry as a string.
     *
     * @throws \Exception
     */
    public function getNextRunDate(): CarbonImmutable
    {
        $formatted = sprintf('%s %s %s %s %s', $this->cron_minute, $this->cron_hour, $this->cron_day_of_month, $this->cron_month, $this->cron_day_of_week);

        return CarbonImmutable::instance((new CronExpression($formatted))->getNextRunDate());
    }

    /**
     * Return a hashid encoded string to represent the ID of the schedule.
     */
    public function getHashidAttribute(): string
    {
        return Container::getInstance()->make(HashidsInterface::class)->encode($this->id);
    }

    /**
     * Return tasks belonging to a schedule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Return the server model that a schedule belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
