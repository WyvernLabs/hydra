<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Hydra: the account has been warned by an admin.
 */
class AccountWarned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reason)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('mail.account_warned.subject'))
            ->greeting(trans('mail.hello_name', ['name' => $notifiable->name_first ?: $notifiable->username]))
            ->line(trans('mail.account_warned.intro'))
            ->line(trans('mail.account_warned.reason', ['reason' => $this->reason]))
            ->line(trans('mail.account_warned.outro'))
            ->action(trans('mail.account_warned.action'), url('/'));
    }
}
