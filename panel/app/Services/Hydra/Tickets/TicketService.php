<?php

namespace Pterodactyl\Services\Hydra\Tickets;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Server;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Models\TicketMessage;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\TicketAttachment;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Services\Hydra\Addons\TicketsAddon;
use Pterodactyl\Services\Hydra\Markdown\MarkdownService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Hydra: support tickets (tickets addon). Users write them from their account,
 * the team answers in the admin area. Images are uploaded first and then
 * linked to the message whose Markdown shows them.
 */
class TicketService
{
    /**
     * Images that were uploaded but never sent are removed after this many hours.
     */
    public const ORPHAN_HOURS = 24;

    public function __construct(
        private ConnectionInterface $connection,
        private TicketsAddon $addon,
        private MarkdownService $markdown,
    ) {
    }

    /**
     * Opens a ticket with its first message.
     *
     * @param array{subject: string, body: string, category?: ?string, language?: ?string, priority?: ?string} $data
     */
    public function create(User $user, array $data, ?Server $server = null): Ticket
    {
        $max = $this->addon->maxOpen();
        if ($max > 0 && Ticket::query()->where('user_id', $user->id)->unclosed()->count() >= $max) {
            throw new ConflictHttpException(trans('client/tickets.errors.max_open', ['count' => $max]));
        }

        return $this->connection->transaction(function () use ($user, $data, $server) {
            /** @var Ticket $ticket */
            $ticket = Ticket::query()->create([
                'user_id' => $user->id,
                'server_id' => $server?->id,
                'category' => Arr::get($data, 'category') ?: null,
                'language' => Arr::get($data, 'language') ?: null,
                'subject' => $data['subject'],
                'priority' => Arr::get($data, 'priority') ?: 'normal',
                'status' => Ticket::STATUS_OPEN,
                'last_reply_at' => now(),
            ]);

            $this->addMessage($ticket, $user, $data['body'], false, false);

            return $ticket;
        });
    }

    /**
     * Adds a message. Answers of the team mark the ticket as answered and mail
     * the user, answers of the user reopen it for the team. Internal notes
     * change nothing.
     */
    public function reply(Ticket $ticket, User $author, string $body, bool $staff, bool $internal = false): TicketMessage
    {
        $internal = $staff && $internal;

        $message = $this->connection->transaction(function () use ($ticket, $author, $body, $staff, $internal) {
            $message = $this->addMessage($ticket, $author, $body, $staff, $internal);

            if (!$internal) {
                $ticket->forceFill([
                    'status' => $staff ? Ticket::STATUS_ANSWERED : Ticket::STATUS_OPEN,
                    'closed_at' => null,
                    'last_reply_at' => now(),
                ])->save();
            }

            return $message;
        });

        return $message;
    }

    public function close(Ticket $ticket): void
    {
        $ticket->forceFill(['status' => Ticket::STATUS_CLOSED, 'closed_at' => now()])->save();
    }

    public function reopen(Ticket $ticket): void
    {
        $ticket->forceFill(['status' => Ticket::STATUS_OPEN, 'closed_at' => null])->save();
    }

    /**
     * Deletes the ticket with its messages and images.
     */
    public function delete(Ticket $ticket): void
    {
        TicketAttachment::query()->where('ticket_id', $ticket->id)->each(fn (TicketAttachment $file) => $this->deleteFile($file));

        $ticket->delete();
    }

    /**
     * Stores an uploaded image. It belongs to the uploader until a message
     * showing it is sent.
     */
    public function upload(User $user, UploadedFile $file): TicketAttachment
    {
        $uuid = Uuid::uuid4()->toString();
        Storage::disk('local')->putFileAs('hydra/tickets', $file, $uuid);

        return TicketAttachment::query()->create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'name' => mb_substr($file->getClientOriginalName() ?: 'image', 0, 191),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Whether the user may see the image: the uploader, the owner of the
     * ticket (not for internal notes) and the team.
     */
    public function canView(User $user, TicketAttachment $file): bool
    {
        if ($file->user_id === $user->id || ($file->ticket_id && $user->hasAdminPermission('tickets.view'))) {
            return true;
        }

        return $file->ticket?->user_id === $user->id && !$file->message?->internal;
    }

    /**
     * Removes images that were uploaded but never sent, and files left behind
     * by tickets the database removed (e.g. with a deleted account).
     */
    public function cleanup(): int
    {
        $count = 0;
        TicketAttachment::query()
            ->whereNull('message_id')
            ->where('created_at', '<', now()->subHours(self::ORPHAN_HOURS))
            ->each(function (TicketAttachment $file) use (&$count) {
                $this->deleteFile($file);
                ++$count;
            });

        $disk = Storage::disk('local');
        $files = collect($disk->files('hydra/tickets'))->keyBy(fn (string $path) => basename($path));
        $known = TicketAttachment::query()->whereIn('uuid', $files->keys())->pluck('uuid')->all();
        foreach ($files->except($known) as $path) {
            if ($disk->lastModified($path) < now()->subHours(self::ORPHAN_HOURS)->getTimestamp()) {
                $disk->delete($path);
                ++$count;
            }
        }

        return $count;
    }

    public function html(TicketMessage $message): string
    {
        return $this->render($message->body);
    }

    /**
     * HTML of ticket Markdown, also for the preview while writing.
     */
    public function render(?string $body): string
    {
        return $this->markdown->renderTicket($body);
    }

    private function addMessage(Ticket $ticket, User $author, string $body, bool $staff, bool $internal): TicketMessage
    {
        /** @var TicketMessage $message */
        $message = $ticket->messages()->create([
            'user_id' => $author->id,
            'body' => $body,
            'staff' => $staff,
            'internal' => $internal,
        ]);

        // Images the author uploaded and shows in this message now belong to it.
        preg_match_all('#/tickets/attachments/([0-9a-f-]{36})#', $body, $matches);
        if (!empty($matches[1])) {
            TicketAttachment::query()
                ->whereIn('uuid', array_unique($matches[1]))
                ->whereNull('message_id')
                ->where('user_id', $author->id)
                ->update(['ticket_id' => $ticket->id, 'message_id' => $message->id]);
        }

        return $message;
    }

    private function deleteFile(TicketAttachment $file): void
    {
        Storage::disk('local')->delete($file->path());
        $file->delete();
    }
}
