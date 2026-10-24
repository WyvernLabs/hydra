<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a support ticket of a user (tickets addon).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $server_id
 * @property string|null $category
 * @property string|null $language
 * @property string $subject
 * @property string $priority
 * @property string $status
 * @property int|null $assigned_to
 * @property \Carbon\Carbon|null $last_reply_at
 * @property \Carbon\Carbon|null $closed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property User $user
 * @property Server|null $server
 * @property User|null $assignee
 * @property \Illuminate\Database\Eloquent\Collection|TicketMessage[] $messages
 */
class Ticket extends Model
{
    public const RESOURCE_NAME = 'ticket';

    // Waiting for the team.
    public const STATUS_OPEN = 'open';
    // The team answered, waiting for the user.
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_ANSWERED, self::STATUS_CLOSED];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $table = 'tickets';

    protected $fillable = [
        'user_id', 'server_id', 'category', 'language', 'subject', 'priority', 'status', 'assigned_to', 'last_reply_at', 'closed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'server_id' => 'integer',
        'assigned_to' => 'integer',
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $attributes = [
        'priority' => 'normal',
        'status' => self::STATUS_OPEN,
    ];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'server_id' => 'nullable|integer',
        'category' => 'nullable|string|max:64',
        'language' => 'nullable|string|max:8',
        'subject' => 'required|string|between:3,191',
        'priority' => 'required|in:low,normal,high,urgent',
        'status' => 'required|in:open,answered,closed',
        'assigned_to' => 'nullable|integer',
        'last_reply_at' => 'nullable|date',
        'closed_at' => 'nullable|date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Tickets that are not closed.
     */
    public function scopeUnclosed(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }
}
