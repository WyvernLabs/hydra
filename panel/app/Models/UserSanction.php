<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a warning or a ban of a user account.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $reason
 * @property int|null $issued_by
 * @property \Carbon\Carbon|null $expires_at
 * @property array|null $suspended_servers
 * @property \Carbon\Carbon|null $lifted_at
 * @property int|null $lifted_by
 * @property \Carbon\Carbon|null $acknowledged_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property User $user
 * @property User|null $issuer
 * @property User|null $lifter
 */
class UserSanction extends Model
{
    public const RESOURCE_NAME = 'user_sanction';

    public const TYPE_WARNING = 'warning';
    public const TYPE_BAN = 'ban';

    protected $table = 'user_sanctions';

    protected $fillable = ['user_id', 'type', 'reason', 'issued_by', 'expires_at', 'suspended_servers'];

    protected $casts = [
        'user_id' => 'integer',
        'issued_by' => 'integer',
        'lifted_by' => 'integer',
        'expires_at' => 'datetime',
        'lifted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'suspended_servers' => 'array',
    ];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'type' => 'required|in:warning,ban',
        'reason' => 'required|string|max:2000',
        'issued_by' => 'nullable|integer',
        'expires_at' => 'nullable|date',
        'suspended_servers' => 'nullable|array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function isBan(): bool
    {
        return $this->type === self::TYPE_BAN;
    }

    /**
     * Whether this ban still applies.
     */
    public function isActive(): bool
    {
        return $this->isBan() && is_null($this->lifted_at) && (is_null($this->expires_at) || $this->expires_at->isFuture());
    }

    /**
     * Bans that apply right now.
     */
    public function scopeActiveBans(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BAN)
            ->whereNull('lifted_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
