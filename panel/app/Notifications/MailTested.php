<?php

namespace Pterodactyl\Notifications;

use Pterodactyl\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class MailTested extends Notification
{
    public function __construct(private User $user)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('mail.test.subject', ['app' => config('app.name')]))
            ->greeting(trans('mail.hello_name', ['name' => $this->user->name]))
            ->line(trans('mail.test.line', ['app' => config('app.name')]));
    }
}
