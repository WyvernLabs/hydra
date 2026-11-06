<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a panel account connected to a Discord account (addon "discord").
 *
 * @property int $id
 * @property int $user_id
 * @property string $discord_id
 * @property string|null $username
 * @property bool $notify_tickets
 * @property string[]|null $roles Discord roles the panel last gave this account
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property User $user
 */
class HydraDiscordLink extends Model
{
    public const RESOURCE_NAME = 'hydra_discord_link';

    protected $table = 'hydra_discord_links';

    protected bool $immutableDates = true;

    protected $fillable = ['user_id', 'discord_id', 'username', 'notify_tickets', 'roles'];

    protected $casts = [
        'user_id' => 'integer',
        'notify_tickets' => 'boolean',
        'roles' => 'array',
    ];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'discord_id' => 'required|string|max:20',
        'username' => 'nullable|string|max:64',
        'notify_tickets' => 'boolean',
        'roles' => 'nullable',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
