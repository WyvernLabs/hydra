<?php

namespace Pterodactyl\Services\Hydra\Discord;

/**
 * Hydra: Discord refused a request or could not be reached (status 0).
 */
class DiscordApiException extends \RuntimeException
{
    // Codes of the Discord API.
    public const UNKNOWN_MEMBER = 10007;
    public const UNKNOWN_USER = 10013;
    public const CANNOT_MESSAGE_USER = 50007;

    public function __construct(public int $status, public ?int $discordCode, string $message)
    {
        parent::__construct($message);
    }
}
