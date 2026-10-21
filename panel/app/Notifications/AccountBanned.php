<?php

namespace Pterodactyl\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Pterodactyl\Services\Hydra\Addons\AutoDeleteAddon;

/**
 * Hydra: the account has been banned by an admin.
 */
class AccountBanned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reason, public ?CarbonInterface $until)
    {
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : null;

        $message = (new MailMessage())
            ->subject(trans('mail.account_banned.subject'))
            ->greeting(trans('mail.hello_name', ['name' => $notifiable->name_first ?: $notifiable->username]))
            ->line(trans('mail.account_banned.intro'))
            ->line(trans('mail.account_banned.reason', ['reason' => $this->reason]))
            ->line($this->until
                ? trans('mail.account_banned.until', ['date' => $this->until->locale($locale ?? 'en')->isoFormat('LLL')])
                : trans('mail.account_banned.permanent'))
            ->line(trans('mail.account_banned.servers'));

        $addon = app(AutoDeleteAddon::class);
        // Only permanently banned accounts are deleted.
        if (!$this->until && $addon->deletesBannedUsers()) {
            $message->line(trans('mail.account_banned.deletion', ['days' => $addon->userDays()]));
        }

        return $message->line(trans('mail.account_banned.outro'));
    }
}
