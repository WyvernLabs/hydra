<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: a message of a ticket, written in Markdown.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $staff
 * @property bool $internal
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Ticket $ticket
 * @property User|null $user
 * @property \Illuminate\Database\Eloquent\Collection|TicketAttachment[] $attachments
 */
class TicketMessage extends Model
{
    public const RESOURCE_NAME = 'ticket_message';

    protected $table = 'ticket_messages';

    protected $fillable = ['ticket_id', 'user_id', 'body', 'staff', 'internal'];

    protected $casts = [
        'ticket_id' => 'integer',
        'user_id' => 'integer',
        'staff' => 'boolean',
        'internal' => 'boolean',
    ];

    public static array $validationRules = [
        'ticket_id' => 'required|integer|exists:tickets,id',
        'user_id' => 'nullable|integer',
        'body' => 'required|string|max:20000',
        'staff' => 'boolean',
        'internal' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'message_id');
    }
}
