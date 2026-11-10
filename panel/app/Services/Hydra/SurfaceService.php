<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: colors and transparency of the boxes and surfaces of the panel
 * (Design > Colors & Logo > Boxes & surfaces).
 *
 * Every box of the client area and the admin area takes its background from
 * the CSS custom properties printed by cssVariables(): the hydra-* classes via
 * --hydra-panel, Tailwind's bg-gray-600…900 and border-gray-600…800 via
 * --hydra-surface-* and --hydra-line-* (see tailwind.config.js and
 * public/hydra/tokens.css). With an opacity below 100 % and a blur the boxes
 * become frosted glass in front of the background image.
 */
class SurfaceService
{
    public const DEFAULTS = [
        'box' => '#1f1f1f',
        'inner' => '#262626',
        'border' => '#2e2e2e',
        'opacity' => 100,
        'border_opacity' => 100,
        'blur' => 0,
    ];

    public const MAX_BLUR = 40;

    /**
     * Opacity of the boxes never goes below this, so text stays readable on
     * any background.
     */
    public const MIN_OPACITY = 10;

    /**
     * Dialogs, dropdowns and tooltips lie on top of other content. They stay at
     * least this opaque so they can still be read with a glass style.
     */
    public const MIN_FLOAT_OPACITY = 88;

    public const COLORS = ['box', 'inner', 'border'];

    public const NUMBERS = ['opacity' => [self::MIN_OPACITY, 100], 'border_opacity' => [0, 100], 'blur' => [0, self::MAX_BLUR]];

    /**
     * Ready-made styles for the admin area. The keys are translated in
     * admin/design.surfaces.presets.
     */
    public const PRESETS = [
        'default' => self::DEFAULTS,
        'glass' => ['box' => '#141414', 'inner' => '#2a2a2a', 'border' => '#ffffff', 'opacity' => 60, 'border_opacity' => 12, 'blur' => 16],
        'clear_glass' => ['box' => '#0c0c0c', 'inner' => '#474747', 'border' => '#ffffff', 'opacity' => 30, 'border_opacity' => 18, 'blur' => 24],
        'midnight' => ['box' => '#141a26', 'inner' => '#1c2433', 'border' => '#263042', 'opacity' => 100, 'border_opacity' => 100, 'blur' => 0],
        'midnight_glass' => ['box' => '#0f1522', 'inner' => '#1f2a3d', 'border' => '#8fb4ff', 'opacity' => 65, 'border_opacity' => 16, 'blur' => 18],
        'black' => ['box' => '#0d0d0d', 'inner' => '#171717', 'border' => '#232323', 'opacity' => 100, 'border_opacity' => 100, 'blur' => 0],
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * The current values, always complete and valid.
     *
     * @return array{box: string, inner: string, border: string, opacity: int, border_opacity: int, blur: int}
     */
    public function values(): array
    {
        return self::normalize((array) config('hydra.surfaces', []));
    }

    public function save(array $values): void
    {
        foreach (self::normalize($values) as $key => $value) {
            if ($value === self::DEFAULTS[$key]) {
                $this->settings->forget("settings::hydra:surfaces:$key");
            } else {
                $this->settings->set("settings::hydra:surfaces:$key", (string) $value);
            }

            config()->set("hydra.surfaces.$key", $value);
        }
    }

    /**
     * Fills in missing or invalid values with the defaults and keeps the
     * numbers in range.
     */
    public static function normalize(array $values): array
    {
        $result = [];
        foreach (self::COLORS as $key) {
            $color = $values[$key] ?? null;
            $result[$key] = BrandingService::isValidColor($color) ? strtolower($color) : self::DEFAULTS[$key];
        }

        foreach (self::NUMBERS as $key => [$min, $max]) {
            $value = $values[$key] ?? null;
            $result[$key] = is_numeric($value) ? max($min, min($max, (int) $value)) : self::DEFAULTS[$key];
        }

        return $result;
    }

    /**
     * CSS custom properties for the boxes, printed into every page right after
     * the accent color.
     */
    public function cssVariables(): string
    {
        $values = $this->values();
        $box = self::hexToRgb($values['box']);
        $inner = self::hexToRgb($values['inner']);
        $border = self::hexToRgb($values['border']);

        // The ratios keep the default look identical to the built-in gray
        // scale (900 = 20, 800 = 31, 700 = 38, 600 = 58).
        $vars = [
            'surface-900' => self::mix($box, -0.355),
            'surface-800' => $box,
            'surface-700' => $inner,
            'surface-650' => self::mix($inner, 0.032),
            'surface-600' => self::mix($inner, 0.093),
            'line' => $border,
            'line-600' => self::mix($border, 0.057),
            'line-700' => self::mix($border, -0.174),
            'line-800' => self::mix($border, -0.326),
        ];

        $lines = [];
        foreach ($vars as $name => $rgb) {
            $lines[] = sprintf('--hydra-%s: %s;', $name, implode(' ', $rgb));
        }

        $alpha = $values['opacity'] / 100;
        $lines[] = '--hydra-surface-alpha: ' . self::number($alpha) . ';';
        $lines[] = '--hydra-float-alpha: ' . self::number(max($alpha, self::MIN_FLOAT_OPACITY / 100)) . ';';
        $lines[] = '--hydra-line-alpha: ' . self::number($values['border_opacity'] / 100) . ';';

        // "none" unless a blur is set: any other backdrop-filter value turns
        // the box into the containing block of fixed elements inside it.
        $lines[] = '--hydra-backdrop: ' . ($values['blur'] > 0 ? sprintf('blur(%dpx) saturate(140%%)', $values['blur']) : 'none') . ';';

        return ':root{' . implode('', $lines) . '}';
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
    }

    private static function hexToRgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    /**
     * Positive ratios mix with white, negative ones with black.
     */
    private static function mix(array $rgb, float $ratio): array
    {
        $target = $ratio >= 0 ? 255 : 0;
        $amount = abs($ratio);

        return array_map(fn (int $channel) => (int) round($channel + ($target - $channel) * $amount), $rgb);
    }
}
