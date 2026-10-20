<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\HtmlString;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Hydra: the six digit code that confirms the email address of a new
 * account (Registration addon).
 */
class RegistrationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public int $minutes)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('mail.registration_code.subject', ['app' => config('app.name'), 'code' => $this->code]))
            ->line(trans('mail.registration_code.intro', ['app' => config('app.name')]))
            ->line(new HtmlString(
                '<span style="display: inline-block; padding: 12px 18px; border-radius: 8px; background: #f2f4f6; color: #1f2933; font-family: monospace; font-size: 28px; font-weight: bold; letter-spacing: 8px;">'
                . e($this->code) . '</span>'
            ))
            ->line(trans('mail.registration_code.lifetime', ['minutes' => $this->minutes]))
            ->line(trans('mail.registration_code.outro'));
    }
}
