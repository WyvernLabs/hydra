<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Http\UploadedFile;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: loading animation of the panel (Designer > Loading animation). One
 * of the templates drawn with CSS (public/hydra/layout.css, ".hydra-loader")
 * or an uploaded GIF. The React spinner (components/elements/Spinner.tsx)
 * reads it from window.SiteConfiguration.loader.
 *
 * Settings: "settings::hydra:loader:style", "settings::hydra:loader:speed",
 * the GIF is stored like the logo ("settings::hydra:branding:loader").
 */
class LoaderDesignService
{
    public const STYLES = ['ring', 'hourglass', 'dots', 'shockwave', 'pulse', 'bars', 'gif'];

    /**
     * Speed in percent of the normal speed.
     */
    public const SPEED = [25, 300];

    public function __construct(private SettingsRepositoryInterface $settings, private BrandingService $branding)
    {
    }

    /**
     * The chosen style; a GIF style without an uploaded GIF falls back to the ring.
     */
    public function style(): string
    {
        $style = (string) config('hydra.loader.style', 'ring');
        if (!in_array($style, self::STYLES, true) || ($style === 'gif' && !$this->hasGif())) {
            return 'ring';
        }

        return $style;
    }

    public function speed(): int
    {
        $speed = config('hydra.loader.speed');

        return is_numeric($speed) ? max(self::SPEED[0], min(self::SPEED[1], (int) $speed)) : 100;
    }

    public function hasGif(): bool
    {
        return !is_null($this->branding->path('loader'));
    }

    public function gifUrl(): ?string
    {
        $path = $this->branding->path('loader');

        return $path ? '/branding/loader?v=' . substr(md5(basename($path)), 0, 8) : null;
    }

    public function save(string $style, int $speed, ?UploadedFile $gif, bool $removeGif): void
    {
        if ($gif) {
            $this->branding->store('loader', $gif);
        } elseif ($removeGif) {
            $this->branding->reset('loader');
        }

        $this->settings->set('settings::hydra:loader:style', $style);
        $this->settings->set('settings::hydra:loader:speed', (string) $speed);
        config()->set('hydra.loader.style', $style);
        config()->set('hydra.loader.speed', $speed);
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.loader).
     */
    public function toFrontend(): array
    {
        return [
            'style' => $this->style(),
            'gif' => $this->style() === 'gif' ? $this->gifUrl() : null,
            'speed' => $this->speed(),
        ];
    }
}
