<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

/**
 * Hydra: Cloudflare Turnstile as captcha for the login, the password reset
 * and the registration.
 */
class TurnstileAddon extends Addon
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Forms that can be protected, in the order they are shown.
     */
    public const FORMS = ['login', 'password', 'register'];

    public function key(): string
    {
        return 'turnstile';
    }

    public function icon(): string
    {
        return 'shield-check';
    }

    public function rules(): array
    {
        return [
            'site_key' => 'nullable|string|max:191',
            'secret_key' => 'nullable|string|max:191',
        ];
    }

    public function encrypted(): array
    {
        return ['secret_key'];
    }

    public function booleans(): array
    {
        return self::FORMS;
    }

    public function missing(): ?string
    {
        if (empty($this->get('site_key')) || empty($this->get('secret_key'))) {
            return trans('admin/addons.turnstile.missing_keys');
        }

        return null;
    }

    public function summary(): array
    {
        $forms = array_filter(self::FORMS, fn (string $form) => (bool) $this->get($form));

        return [
            empty($forms)
                ? trans('admin/addons.turnstile.no_forms')
                : implode(', ', array_map(fn (string $form) => trans("admin/addons.turnstile.forms.$form"), $forms)),
        ];
    }

    /**
     * Whether the given form has to send a valid Turnstile token.
     */
    public function protects(string $form): bool
    {
        return $this->enabled() && is_null($this->missing()) && (bool) $this->get($form);
    }

    /**
     * Asks Cloudflare whether a token sent by the browser is valid.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, array_filter([
                'secret' => $this->get('secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.turnstile).
     */
    public function toArray(): array
    {
        return [
            'siteKey' => $this->enabled() ? (string) $this->get('site_key') : '',
            'forms' => collect(self::FORMS)->mapWithKeys(fn (string $form) => [$form => $this->protects($form)])->all(),
        ];
    }
}
