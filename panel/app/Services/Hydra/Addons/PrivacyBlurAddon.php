<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: privacy blur. Server addresses (IPs, hostnames of allocations) and
 * email addresses are blurred until the mouse rests on them, e.g. for
 * streams, screenshots and screen sharing. public/hydra/privacy.js finds the
 * values on every page; spots with hostnames carry the class
 * "hydra-private-ip" in the React code.
 */
class PrivacyBlurAddon extends Addon
{
    public const STRENGTH = [2, 16];

    public function key(): string
    {
        return 'privacy_blur';
    }

    public function icon(): string
    {
        return 'eye-off';
    }

    public function rules(): array
    {
        return [
            'strength' => 'required|integer|between:' . implode(',', self::STRENGTH),
        ];
    }

    public function booleans(): array
    {
        return ['ips', 'emails', 'admin'];
    }

    public function summary(): array
    {
        $kinds = array_keys(array_filter($this->kinds()));
        if (empty($kinds)) {
            return [trans('admin/addons.privacy_blur.summary_nothing')];
        }

        return [
            implode(', ', array_map(fn (string $kind) => trans("admin/addons.privacy_blur.summary_$kind"), $kinds)),
            $this->get('admin', true) ? trans('admin/addons.privacy_blur.summary_admin') : trans('admin/addons.privacy_blur.summary_client'),
        ];
    }

    /**
     * Which kinds of values are blurred.
     *
     * @return array{ip: bool, email: bool}
     */
    public function kinds(): array
    {
        return [
            'ip' => (bool) $this->get('ips', true),
            'email' => (bool) $this->get('emails', true),
        ];
    }

    public function strength(): int
    {
        return max(self::STRENGTH[0], min(self::STRENGTH[1], (int) $this->get('strength', 5)));
    }

    /**
     * Settings for public/hydra/privacy.js on the current page, or null when
     * nothing is blurred there.
     */
    public function forPage(bool $admin): ?array
    {
        if (!$this->enabled() || ($admin && !$this->get('admin', true))) {
            return null;
        }

        $kinds = $this->kinds();

        return in_array(true, $kinds, true) ? $kinds + ['strength' => $this->strength()] : null;
    }
}
