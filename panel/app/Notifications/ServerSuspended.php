<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Pterodactyl\Services\Hydra\Addons\AutoDeleteAddon;

/**
 * Hydra: a server of the user has been suspended. Not sent when a ban
 * suspends the servers, the ban email says so already.
 */
class ServerSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $server)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject(trans('mail.server_suspended.subject', ['name' => $this->server]))
            ->greeting(trans('mail.hello_name', ['name' => $notifiable->name_first ?: $notifiable->username]))
            ->line(trans('mail.server_suspended.intro', ['name' => $this->server]))
            ->line(trans('mail.server_suspended.access'));

        $addon = app(AutoDeleteAddon::class);
        if ($addon->deletesSuspendedServers()) {
            $message->line(trans('mail.server_suspended.deletion', ['days' => $addon->serverDays()]));
        }

        return $message->line(trans('mail.server_suspended.outro'));
    }
}
