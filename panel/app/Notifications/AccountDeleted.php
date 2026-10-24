<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Hydra: the account has been deleted, by its owner, an admin or the
 * autodelete addon. Sent to the address alone, since the user is gone by
 * the time the queue sends it.
 */
class AccountDeleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $name)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('mail.account_deleted.subject', ['app' => config('app.name')]))
            ->greeting(trans('mail.hello_name', ['name' => $this->name]))
            ->line(trans('mail.account_deleted.intro', ['app' => config('app.name')]))
            ->line(trans('mail.account_deleted.data'))
            ->line(trans('mail.account_deleted.outro'));
    }
}
