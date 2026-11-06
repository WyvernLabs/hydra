<?php

namespace Pterodactyl\Services\Hydra\Discord;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\HydraDiscordLink;
use Pterodactyl\Services\Hydra\Addons\DiscordAddon;

/**
 * Hydra: tells linked users about new answers in tickets. The messages go out
 * after the response, so a slow Discord never holds up the person answering.
 */
class DiscordNotifier
{
    public function __construct(private DiscordAddon $addon, private DiscordClient $client)
    {
    }

    /**
     * The owner hears about answers of the team, the assigned team member about
     * answers of the owner. Nobody is told about their own answer.
     */
    public function ticketAnswered(Ticket $ticket, User $author, bool $staff): void
    {
        if (!$this->addon->enabled() || !$this->addon->notifiesTickets()) {
            return;
        }

        $recipient = $staff ? $ticket->user_id : $ticket->assigned_to;
        if (is_null($recipient) || $recipient === $author->id) {
            return;
        }

        $link = HydraDiscordLink::query()->with('user')->where('user_id', $recipient)->where('notify_tickets', true)->first();
        if (is_null($link)) {
            return;
        }

        $path = $staff ? "/tickets/$ticket->id" : "/admin/tickets/view/$ticket->id";
        $text = trans('discord.ticket_message', [
            'panel' => config('app.name', 'Hydra'),
            'subject' => $ticket->subject,
            'url' => rtrim((string) config('app.url'), '/') . $path,
        ], $link->user->language);

        app()->terminating(function () use ($link, $text) {
            try {
                $this->client->directMessage($link->discord_id, $text);
            } catch (DiscordApiException $exception) {
                Log::info('Discord ticket message could not be sent: ' . $exception->getMessage());
            }
        });
    }
}
