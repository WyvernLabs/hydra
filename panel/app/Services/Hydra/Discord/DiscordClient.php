<?php

namespace Pterodactyl\Services\Hydra\Discord;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Services\Hydra\Addons\DiscordAddon;

/**
 * Hydra: the requests the panel makes to the REST API of Discord as the bot.
 */
class DiscordClient
{
    private const BASE_URL = 'https://discord.com/api/v10';

    // Longest wait in seconds when Discord asks to slow down, and how often.
    private const MAX_RETRY_WAIT = 10;
    private const MAX_RETRIES = 3;

    public function __construct(private DiscordAddon $addon)
    {
    }

    /**
     * The bot itself.
     */
    public function me(): array
    {
        return $this->request('get', '/users/@me')->json();
    }

    public function guild(string $guild): array
    {
        return $this->request('get', "/guilds/$guild")->json();
    }

    /**
     * @return array<int, array> all roles of the Discord server
     */
    public function roles(string $guild): array
    {
        return $this->request('get', "/guilds/$guild/roles")->json();
    }

    /**
     * Sends a direct message. Returns the recipient, as far as Discord tells.
     */
    public function directMessage(string $user, string $text): array
    {
        $channel = $this->request('post', '/users/@me/channels', ['recipient_id' => $user])->json();

        // Nobody gets pinged by names in the text, e.g. in a ticket subject.
        $this->request('post', "/channels/{$channel['id']}/messages", ['content' => $text, 'allowed_mentions' => ['parse' => []]]);

        return $channel['recipients'][0] ?? [];
    }

    /**
     * The member on the Discord server, null while the user has not joined it.
     */
    public function member(string $guild, string $user): ?array
    {
        try {
            return $this->request('get', "/guilds/$guild/members/$user")->json();
        } catch (DiscordApiException $exception) {
            if (in_array($exception->discordCode, [DiscordApiException::UNKNOWN_MEMBER, DiscordApiException::UNKNOWN_USER], true)) {
                return null;
            }

            throw $exception;
        }
    }

    public function addRole(string $guild, string $user, string $role): void
    {
        $this->request('put', "/guilds/$guild/members/$user/roles/$role");
    }

    public function removeRole(string $guild, string $user, string $role): void
    {
        $this->request('delete', "/guilds/$guild/members/$user/roles/$role");
    }

    /**
     * @throws DiscordApiException
     */
    private function request(string $method, string $path, array $data = []): Response
    {
        for ($attempt = 0; ; ++$attempt) {
            try {
                $response = Http::withHeaders(['Authorization' => 'Bot ' . $this->addon->token()])
                    ->withUserAgent('DiscordBot (https://github.com/WyvernLabs/hydra, 1.0)')
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->{$method}(self::BASE_URL . $path, $data);
            } catch (ConnectionException $exception) {
                throw new DiscordApiException(0, null, $exception->getMessage());
            }

            if ($response->status() === 429 && $attempt < self::MAX_RETRIES) {
                $wait = (float) ($response->json('retry_after') ?? 1);
                if ($wait <= self::MAX_RETRY_WAIT) {
                    usleep((int) ($wait * 1000000));
                    continue;
                }
            }

            if ($response->failed()) {
                throw new DiscordApiException($response->status(), $response->json('code'), (string) ($response->json('message') ?? "HTTP {$response->status()}"));
            }

            return $response;
        }
    }
}
