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
     * Credit in the footer of the admin area. WyvernLabs is a registered
     * trademark; unlike the rest of the branding this text is not configurable
     * and must not be removed there. Pages for users and emails do not show
     * it (their footer text is set under Designer > Metadata).
     */
    public const CREDIT = 'Hydra by WyvernLabs®';

    public const DEFAULT_ACCENT = '#74c236';

    public const DEFAULT_LOGO = '/hydra/logo.svg';

    public const DEFAULT_FAVICON = '/hydra/favicon.svg';

    /**
     * Allowed size of the logo in the sidebar in pixels (Designer > Images).
     */
    public const LOGO_WIDTH = [16, 600];

    public const LOGO_HEIGHT = [16, 300];

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

    /**
     * Whether logo and egg pictures are shielded against "save image",
     * dragging and long press (Design > Colors & Logo). On by default.
     */
    public function protectsImages(): bool
    {
        return filter_var(config('hydra.branding.protect', true) ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    public function setProtectImages(bool $protect): void
    {
        if ($protect) {
            $this->settings->forget('settings::hydra:branding:protect');
        } else {
            $this->settings->set('settings::hydra:branding:protect', 'false');
        }

        config()->set('hydra.branding.protect', $protect);
    }

    /**
     * CSS class for protected pictures, empty while the protection is off.
     */
    public function protectedClass(): string
    {
        return $this->protectsImages() ? 'hydra-protected' : '';
    }

    /**
     * Fixed width and height of the logo in the sidebar in pixels; null means
     * automatic (fits into the sidebar like before).
     *
     * @return array{width: ?int, height: ?int}
     */
    public function logoSize(): array
    {
        $size = fn (string $key, array $range) => is_numeric($value = config("hydra.branding.$key"))
            ? max($range[0], min($range[1], (int) $value))
            : null;

        return ['width' => $size('logo_width', self::LOGO_WIDTH), 'height' => $size('logo_height', self::LOGO_HEIGHT)];
    }

    public function setLogoSize(?int $width, ?int $height): void
    {
        foreach (['logo_width' => $width, 'logo_height' => $height] as $key => $value) {
            if (is_null($value)) {
                $this->settings->forget("settings::hydra:branding:$key");
            } else {
                $this->settings->set("settings::hydra:branding:$key", (string) $value);
            }
            config()->set("hydra.branding.$key", $value);
        }
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
            'protect' => $this->protectsImages(),
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

        // Logo in the sidebar, only when the admin set a fixed size.
        $logo = $this->logoSize();
        if ($logo['width']) {
            $lines[] = "--hydra-logo-width: {$logo['width']}px;";
        }
        if ($logo['height']) {
            $lines[] = "--hydra-logo-height: {$logo['height']}px;";
        }
        if ($logo['width'] || $logo['height']) {
            $lines[] = '--hydra-logo-max-height: none;';
        }

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
