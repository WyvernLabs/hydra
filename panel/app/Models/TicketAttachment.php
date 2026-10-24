<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: an image uploaded for a ticket. It belongs to its uploader until the
 * message showing it is sent, then to that message.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $ticket_id
 * @property int|null $message_id
 * @property int|null $user_id
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Ticket|null $ticket
 * @property TicketMessage|null $message
 */
class TicketAttachment extends Model
{
    public const RESOURCE_NAME = 'ticket_attachment';

    // Images only. SVG is left out since it can carry scripts.
    public const MIMES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    protected $table = 'ticket_attachments';

    protected $fillable = ['uuid', 'ticket_id', 'message_id', 'user_id', 'name', 'mime', 'size'];

    protected $casts = [
        'ticket_id' => 'integer',
        'message_id' => 'integer',
        'user_id' => 'integer',
        'size' => 'integer',
    ];

    public static array $validationRules = [
        'uuid' => 'required|string|size:36',
        'ticket_id' => 'nullable|integer',
        'message_id' => 'nullable|integer',
        'user_id' => 'nullable|integer',
        'name' => 'required|string|max:191',
        'mime' => 'required|string|max:64',
        'size' => 'required|integer|min:0',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'message_id');
    }

    /**
     * Path of the file on the "local" disk.
     */
    public function path(): string
    {
        return 'hydra/tickets/' . $this->uuid;
    }

    /**
     * Address of the image, as written into the Markdown.
     */
    public function url(): string
    {
        return '/tickets/attachments/' . $this->uuid;
    }
}
