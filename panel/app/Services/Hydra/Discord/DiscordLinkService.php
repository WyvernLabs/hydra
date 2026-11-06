<?php

namespace Pterodactyl\Services\Hydra\Discord;

use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\HydraDiscordLink;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\DiscordAddon;

/**
 * Hydra: connects a panel account to a Discord account. The bot sends a code to
 * the Discord account, entering it in the panel proves that it belongs to the user.
 */
class DiscordLinkService
{
    public const CODE_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;

    // Without O, 0, I and 1, which are easily mixed up.
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 8;

    // A Discord account gets at most one code in this time, whoever asks for it.
    private const TARGET_COOLDOWN = 60;

    public function __construct(private DiscordAddon $addon, private DiscordClient $client, private DiscordRoleService $roles)
    {
    }

    /**
     * Sends a new code, which replaces an earlier one.
     *
     * @throws DisplayException
     */
    public function sendCode(User $user, string $discordId): void
    {
        if (HydraDiscordLink::query()->where('discord_id', $discordId)->where('user_id', '!=', $user->id)->exists()) {
            throw new DisplayException(trans('discord.errors.taken'));
        }
        if (HydraDiscordLink::query()->where('user_id', $user->id)->exists()) {
            throw new DisplayException(trans('discord.errors.already_linked'));
        }
        if (!Cache::add("hydra:discord:target:$discordId", true, self::TARGET_COOLDOWN)) {
            throw new DisplayException(trans('discord.errors.cooldown', ['seconds' => self::TARGET_COOLDOWN]));
        }

        $code = $this->generateCode();
        $text = trans('discord.code_message', [
            'panel' => config('app.name', 'Hydra'),
            'username' => $user->username,
            'code' => $code,
            'minutes' => self::CODE_MINUTES,
        ], $user->language);

        try {
            $recipient = $this->client->directMessage($discordId, $text);
        } catch (DiscordApiException $exception) {
            Cache::forget("hydra:discord:target:$discordId");

            throw new DisplayException($this->sendError($exception));
        }

        Cache::put($this->key($user), [
            'hash' => hash('sha256', $code),
            'discord_id' => $discordId,
            'username' => $recipient['username'] ?? null,
            'expires_at' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
            'attempts' => 0,
        ], now()->addMinutes(self::CODE_MINUTES));
    }

    /**
     * The code waiting to be entered, if there is one.
     */
    public function pending(User $user): ?array
    {
        $pending = Cache::get($this->key($user));
        if (!is_array($pending) || $pending['expires_at'] <= now()->getTimestamp()) {
            return null;
        }

        return ['discord_id' => $pending['discord_id'], 'expires_at' => $pending['expires_at']];
    }

    /**
     * Checks the code and saves the connection.
     *
     * @throws DisplayException
     */
    public function confirm(User $user, string $code): HydraDiscordLink
    {
        $pending = Cache::get($this->key($user));
        if (!is_array($pending) || $pending['expires_at'] <= now()->getTimestamp()) {
            Cache::forget($this->key($user));

            throw new DisplayException(trans('discord.errors.code_expired'));
        }

        $code = strtoupper(preg_replace('/\s+/', '', $code));
        if (!hash_equals($pending['hash'], hash('sha256', $code))) {
            if (++$pending['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($this->key($user));

                throw new DisplayException(trans('discord.errors.too_many_attempts'));
            }
            Cache::put($this->key($user), $pending, now()->setTimestamp($pending['expires_at']));

            throw new DisplayException(trans('discord.errors.code_wrong'));
        }

        // Someone else may have connected this Discord account in the meantime.
        if (HydraDiscordLink::query()->where('discord_id', $pending['discord_id'])->where('user_id', '!=', $user->id)->exists()) {
            Cache::forget($this->key($user));

            throw new DisplayException(trans('discord.errors.taken'));
        }

        /** @var HydraDiscordLink $link */
        $link = HydraDiscordLink::query()->updateOrCreate(['user_id' => $user->id], [
            'discord_id' => $pending['discord_id'],
            'username' => $pending['username'],
        ]);
        Cache::forget($this->key($user));

        Activity::event('user:discord.linked')->property('discord_id', $link->discord_id)->log();
        $this->quietly(fn () => $this->roles->sync($link));

        return $link;
    }

    public function cancel(User $user): void
    {
        Cache::forget($this->key($user));
    }

    /**
     * Removes the connection and the roles that came with it.
     */
    public function unlink(User $user): void
    {
        $link = HydraDiscordLink::query()->where('user_id', $user->id)->first();
        if (is_null($link)) {
            return;
        }

        $this->quietly(fn () => $this->roles->removeAll($link));
        $link->delete();

        Activity::event('user:discord.unlinked')->property('discord_id', $link->discord_id)->log();
    }

    /**
     * The connection works even when Discord is down for a moment; the regular
     * sync brings the roles in line later.
     */
    private function quietly(\Closure $callback): void
    {
        try {
            $callback();
        } catch (DiscordApiException $exception) {
            Log::warning('Discord roles could not be updated: ' . $exception->getMessage());
        }
    }

    private function sendError(DiscordApiException $exception): string
    {
        Log::info("Discord code could not be sent ($exception->status): " . $exception->getMessage());

        return match (true) {
            $exception->discordCode === DiscordApiException::CANNOT_MESSAGE_USER => trans('discord.errors.dm_closed'),
            $exception->discordCode === DiscordApiException::UNKNOWN_USER,
            $exception->status === 400,
            $exception->status === 404 => trans('discord.errors.unknown_user'),
            default => trans('discord.errors.unavailable'),
        };
    }

    private function generateCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    private function key(User $user): string
    {
        return "hydra:discord:code:$user->id";
    }
}
