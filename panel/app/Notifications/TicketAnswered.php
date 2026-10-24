<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Pterodactyl\Models\Ticket;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Hydra: the team answered a ticket of the user (tickets addon).
 */
class TicketAnswered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('mail.ticket_answered.subject', ['id' => $this->ticket->id]))
            ->greeting(trans('mail.hello_name', ['name' => $notifiable->name_first ?: $notifiable->username]))
            ->line(trans('mail.ticket_answered.intro', ['subject' => $this->ticket->subject]))
            ->action(trans('mail.ticket_answered.action'), url('/account/tickets/' . $this->ticket->id));
    }
}
