<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Central place for everything the panel needs to know about its appearance:
 * accent color (and the generated color scale), logo and favicon.
 *
 * The values are loaded into the "hydra.branding" config by the
 * SettingsServiceProvider, so reading them never hits the database twice.
 */
class BrandingService
{
    /**
     * Credit in the footer of every page and email. WyvernLabs is a registered
     * trademark; unlike the rest of the branding this text is not configurable
     * and must not be removed. The React frontend has the same text in
     * resources/scripts/components/hydra/Footer.tsx.
     */
    public const CREDIT = 'Hydra by WyvernLabs®';

    public const DEFAULT_ACCENT = '#74c236';

    public const DEFAULT_LOGO = '/hydra/logo.svg';

    public const DEFAULT_FAVICON = '/hydra/favicon.svg';

    /**
     * Mixing ratios used to derive the 50–900 scale from the accent color.
     * Positive values mix with white, negative values mix with black.
     */
    private const SCALE = [
        50 => 0.9, 100 => 0.8, 200 => 0.6, 300 => 0.4, 400 => 0.2,
        500 => 0.0, 600 => -0.15, 700 => -0.3, 800 => -0.45, 900 => -0.6,
    ];

    public const MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public static function directory(): string
    {
        return storage_path('app/hydra/branding');
    }

    public function accent(): string
    {
        $accent = (string) config('hydra.branding.accent');

        return self::isValidColor($accent) ? strtolower($accent) : self::DEFAULT_ACCENT;
    }

    public function logoUrl(): string
    {
        return $this->assetUrl('logo', self::DEFAULT_LOGO);
    }

    public function faviconUrl(): string
    {
        return $this->assetUrl('favicon', self::DEFAULT_FAVICON);
    }

    /**
     * Returns the absolute path of an uploaded branding image, or null when
     * the bundled default is in use.
     */
    public function path(string $type): ?string
    {
        $file = config("hydra.branding.$type");
        if (empty($file) || basename($file) !== $file) {
            return null;
        }

        $path = self::directory() . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * Stores an uploaded logo or favicon and replaces the previous one.
     */
    public function store(string $type, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!array_key_exists($extension, self::MIME_TYPES)) {
            $extension = 'png';
        }

        $name = $type . '-' . Str::random(12) . '.' . $extension;
        if (!is_dir(self::directory())) {
            mkdir(self::directory(), 0755, true);
        }

        $file->move(self::directory(), $name);
        $this->reset($type);
        $this->settings->set("settings::hydra:branding:$type", $name);
        config()->set("hydra.branding.$type", $name);
    }

    /**
     * Removes an uploaded logo or favicon so the default is used again.
     */
    public function reset(string $type): void
    {
        if ($path = $this->path($type)) {
            @unlink($path);
        }

        $this->settings->forget("settings::hydra:branding:$type");
        config()->set("hydra.branding.$type", null);
    }

    public function setAccent(string $color): void
    {
        $color = strtolower($color);
        $this->settings->set('settings::hydra:branding:accent', $color);
        config()->set('hydra.branding.accent', $color);
    }

    /**
     * Data handed to the React frontend (window.SiteConfiguration.branding).
     */
    public function toArray(): array
    {
        return [
            'accent' => $this->accent(),
            'logo' => $this->logoUrl(),
            'favicon' => $this->faviconUrl(),
        ];
    }

    /**
     * CSS custom properties for the accent color. They are printed into every
     * page (client area and admin area) so both share the same colors.
     */
    public function cssVariables(): string
    {
        [$r, $g, $b] = self::hexToRgb($this->accent());

        $lines = [];
        foreach (self::SCALE as $step => $ratio) {
            $lines[] = sprintf('--hydra-accent-%d: %s;', $step, implode(' ', self::mix([$r, $g, $b], $ratio)));
        }

        // Text on accent colored surfaces needs to stay readable for any
        // color an admin might pick, so decide on dark or light ink.
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        $lines[] = '--hydra-accent-ink: ' . ($luminance > 0.55 ? '15 26 6' : '255 255 255') . ';';

        return ':root{' . implode('', $lines) . '}';
    }

    public static function isValidColor(?string $color): bool
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }

    private function assetUrl(string $type, string $default): string
    {
        $file = $this->path($type);

        return $file ? sprintf('/branding/%s?v=%s', $type, substr(md5(basename($file)), 0, 8)) : $default;
    }

    private static function hexToRgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    private static function mix(array $rgb, float $ratio): array
    {
        $target = $ratio >= 0 ? 255 : 0;
        $amount = abs($ratio);

        return array_map(fn (int $channel) => (int) round($channel + ($target - $channel) * $amount), $rgb);
    }
}
