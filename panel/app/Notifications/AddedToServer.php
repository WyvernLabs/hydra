<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AddedToServer extends Notification implements ShouldQueue
{
    use Queueable;

    public object $server;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $server)
    {
        $this->server = (object) $server;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('mail.added_to_server.subject'))
            ->greeting(trans('mail.hello_name', ['name' => $this->server->user]))
            ->line(trans('mail.added_to_server.intro'))
            ->line(trans('mail.added_to_server.server', ['name' => $this->server->name]))
            ->action(trans('mail.added_to_server.action'), url('/server/' . $this->server->uuidShort));
    }
}
