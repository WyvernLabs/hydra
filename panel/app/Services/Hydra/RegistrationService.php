<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Cache\Repository;
use Pterodactyl\Notifications\RegistrationCode;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Hydra\Addons\RegistrationAddon;

/**
 * Hydra: accounts that visitors create themselves (Registration addon).
 *
 * 1. sendCode():   a six digit code is mailed to the address.
 * 2. verifyCode(): a correct code marks the address as confirmed in the session.
 * 3. register():   the account is created for the confirmed address.
 */
class RegistrationService
{
    public const SESSION_KEY = 'hydra_registration';

    /**
     * Seconds before another code can be sent to the same address.
     */
    public const RESEND_COOLDOWN = 60;

    /**
     * Wrong guesses before a code becomes invalid.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Minutes the confirmed address stays in the session.
     */
    public const VERIFIED_LIFETIME = 30;

    public function __construct(
        private Repository $cache,
        private RegistrationAddon $addon,
        private UserCreationService $creationService,
    ) {
    }

    /**
     * @throws DisplayException
     */
    public function sendCode(string $email): void
    {
        $email = Str::lower(trim($email));

        if (!$this->addon->allowsEmail($email)) {
            throw new DisplayException(trans('auth.register.domain_not_allowed'));
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new DisplayException(trans('auth.register.email_taken'));
        }

        $cooldown = $this->cache->get($this->key('cooldown', $email));
        if (!is_null($cooldown) && $cooldown > time()) {
            throw new DisplayException(trans('auth.register.wait', ['seconds' => $cooldown - time()]));
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = $this->addon->codeLifetime();

        $this->cache->put($this->key('code', $email), [
            'hash' => $this->hash($code),
            'attempts' => 0,
            'expires_at' => time() + $minutes * 60,
        ], $minutes * 60);
        $this->cache->put($this->key('cooldown', $email), time() + self::RESEND_COOLDOWN, self::RESEND_COOLDOWN);

        Notification::route('mail', $email)
            ->notify((new RegistrationCode($code, $minutes))->locale(app()->getLocale()));
    }

    /**
     * @throws DisplayException
     */
    public function verifyCode(Session $session, string $email, string $code): void
    {
        $email = Str::lower(trim($email));
        $key = $this->key('code', $email);
        $entry = $this->cache->get($key);

        if (!is_array($entry) || $entry['expires_at'] <= time()) {
            throw new DisplayException(trans('auth.register.code_expired'));
        }

        if (!hash_equals($entry['hash'], $this->hash(trim($code)))) {
            if (++$entry['attempts'] >= self::MAX_ATTEMPTS) {
                $this->cache->forget($key);

                throw new DisplayException(trans('auth.register.too_many_attempts'));
            }

            // Keep the remaining lifetime of the code.
            $this->cache->put($key, $entry, max(1, $entry['expires_at'] - time()));

            throw new DisplayException(trans('auth.register.code_invalid'));
        }

        $this->cache->forget($key);
        $session->put(self::SESSION_KEY, [
            'email' => $email,
            'expires_at' => time() + self::VERIFIED_LIFETIME * 60,
        ]);
    }

    /**
     * The address confirmed in this session, or null.
     */
    public function verifiedEmail(Session $session): ?string
    {
        $data = $session->get(self::SESSION_KEY);
        if (!is_array($data) || ($data['expires_at'] ?? 0) < time()) {
            return null;
        }

        return $data['email'] ?? null;
    }

    /**
     * @throws DisplayException
     * @throws \Throwable
     */
    public function register(Session $session, array $data): User
    {
        $email = $this->verifiedEmail($session);
        if (is_null($email)) {
            throw new DisplayException(trans('auth.register.not_verified'));
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new DisplayException(trans('auth.register.email_taken'));
        }

        $user = $this->creationService->handle([
            'email' => $email,
            'username' => $data['username'],
            'name_first' => $data['name_first'],
            'name_last' => $data['name_last'],
            'password' => $data['password'],
            'root_admin' => false,
            'language' => app()->getLocale(),
        ], false);

        $session->forget(self::SESSION_KEY);

        return $user;
    }

    private function key(string $type, string $email): string
    {
        return "hydra:registration:$type:" . sha1($email);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
