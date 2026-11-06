<?php

namespace Pterodactyl\Services\Hydra\Effects;

use Pterodactyl\Models\User;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: seasonal effects of the panel (Design > Effects). The admins pick the
 * event, each user can switch its parts off. The choices of a user are kept per
 * event, also while another event is running. Drawn by public/hydra/effects.js.
 */
class SeasonalEffects
{
    public const OFF = 'off';

    public const DECOR = 'decor';
    public const PARTICLES = 'particles';
    public const FIREWORKS = 'fireworks';

    /**
     * The events with the parts a user can switch off.
     */
    public const EVENTS = [
        'halloween' => [self::DECOR, self::PARTICLES],
        'christmas' => [self::DECOR, self::PARTICLES],
        'newyear' => [self::DECOR, self::PARTICLES, self::FIREWORKS],
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * The running event, or "off".
     */
    public function event(): string
    {
        $event = (string) config('hydra.effects.event', self::OFF);

        return array_key_exists($event, self::EVENTS) ? $event : self::OFF;
    }

    public function setEvent(string $event): void
    {
        $this->settings->set('settings::hydra:effects:event', $event);
        config()->set('hydra.effects.event', $event);
    }

    /**
     * The parts of the running event a user can switch off.
     *
     * @return string[]
     */
    public function options(): array
    {
        return self::EVENTS[$this->event()] ?? [];
    }

    /**
     * Which parts of the running event are on for the user. Everything is on
     * until it is switched off, and for visitors who are not logged in.
     *
     * @return array<string, bool>
     */
    public function values(?User $user): array
    {
        $saved = $user?->hydra_effects[$this->event()] ?? [];

        $values = [];
        foreach ($this->options() as $option) {
            $values[$option] = (bool) ($saved[$option] ?? true);
        }

        return $values;
    }

    /**
     * Saves the choices for the running event, those of other events stay.
     *
     * @param array<string, mixed> $values
     */
    public function save(User $user, array $values): void
    {
        $all = $user->hydra_effects ?? [];
        foreach ($this->options() as $option) {
            if (array_key_exists($option, $values)) {
                $all[$this->event()][$option] = (bool) $values[$option];
            }
        }

        $user->forceFill(['hydra_effects' => $all])->save();
    }

    /**
     * Data for the page, null while no event runs so that nothing is loaded.
     */
    public function forPage(?User $user): ?array
    {
        $event = $this->event();

        return $event === self::OFF ? null : ['event' => $event, 'values' => $this->values($user)];
    }
}
