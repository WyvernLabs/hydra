<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Services\Hydra\Addons\PwaAddon;

/**
 * Hydra: manifest and icons of the installable app (addon "pwa"). Without an
 * uploaded icon, the Hydra hexagon is drawn in the accent colour.
 */
class PwaService
{
    public const SIZES = [192, 512];

    public function __construct(private PwaAddon $addon, private BrandingService $branding)
    {
    }

    public static function directory(): string
    {
        return storage_path('app/hydra/pwa');
    }

    public function manifest(): array
    {
        $version = $this->version();
        $icons = [];
        foreach (self::SIZES as $size) {
            $icons[] = ['src' => "/pwa/icon-$size.png?v=$version", 'sizes' => "{$size}x$size", 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => "/pwa/maskable-$size.png?v=$version", 'sizes' => "{$size}x$size", 'type' => 'image/png', 'purpose' => 'maskable'];
        }

        return [
            'id' => '/',
            'name' => $this->addon->appName(),
            'short_name' => $this->addon->shortName(),
            'lang' => config('app.locale', 'en'),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            // The tab bar sits in the title bar when the user hides it.
            'display_override' => ['window-controls-overlay', 'standalone'],
            'background_color' => $this->addon->backgroundColor(),
            'theme_color' => $this->addon->themeColor(),
            'icons' => $icons,
            // Links to the panel open in the running app, which shows them in a tab.
            'launch_handler' => ['client_mode' => ['focus-existing', 'auto']],
        ];
    }

    /**
     * Path of the icon in the size, drawn when it does not exist yet.
     */
    public function icon(int $size, bool $maskable): string
    {
        $path = self::directory() . '/' . ($maskable ? 'maskable' : 'icon') . "-$size.png";
        if (!is_file($path)) {
            $this->ensureDirectory();
            $image = $this->draw($size, $maskable);
            imagepng($image, $path);
            imagedestroy($image);
        }

        return $path;
    }

    /**
     * Keeps the uploaded picture as a square PNG of at most 1024 pixels.
     *
     * @throws ValidationException
     */
    public function storeIcon(UploadedFile $file): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (!$source) {
            throw ValidationException::withMessages(['icon_upload' => trans('admin/addons.pwa.icon_invalid')]);
        }

        $side = min(imagesx($source), imagesy($source));
        $target = min(1024, $side);
        $image = $this->canvas($target, null);
        imagecopyresampled($image, $source, 0, 0, intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2), $target, $target, $side, $side);

        $this->ensureDirectory();
        imagepng($image, self::directory() . '/source.png');
        imagedestroy($image);
        imagedestroy($source);
        $this->clearIcons();
    }

    public function removeIcon(): void
    {
        @unlink(self::directory() . '/source.png');
        $this->clearIcons();
    }

    public function hasIcon(): bool
    {
        return is_file(self::directory() . '/source.png');
    }

    /**
     * Removes the drawn icons, e.g. after the colours changed.
     */
    public function clearIcons(): void
    {
        foreach (array_merge(glob(self::directory() . '/icon-*.png') ?: [], glob(self::directory() . '/maskable-*.png') ?: []) as $file) {
            @unlink($file);
        }
    }

    /**
     * Changes whenever the icons look different, so the app picks up new ones.
     */
    public function version(): string
    {
        $source = self::directory() . '/source.png';

        return substr(md5(implode('|', [
            is_file($source) ? filemtime($source) : 0,
            $this->addon->backgroundColor(),
            $this->branding->accent(),
        ])), 0, 8);
    }

    private function draw(int $size, bool $maskable): \GdImage
    {
        $image = $this->canvas($size, $this->addon->backgroundColor());
        $source = self::directory() . '/source.png';

        if (is_file($source) && ($picture = @imagecreatefrompng($source))) {
            // Maskable icons are cut to a circle by some systems: keep the picture in the safe zone.
            $inner = $maskable ? (int) round($size * 0.8) : $size;
            $offset = intdiv($size - $inner, 2);
            imagecopyresampled($image, $picture, $offset, $offset, 0, 0, $inner, $inner, imagesx($picture), imagesy($picture));
            imagedestroy($picture);

            return $image;
        }

        // Drawn four times as large and scaled down, since GD has no smooth thick lines.
        $scale = 4;
        $big = $size * $scale;
        $canvas = $this->canvas($big, $this->addon->backgroundColor());
        $accent = $this->branding->accent();
        $box = $big * ($maskable ? 0.62 : 0.86);
        $unit = $box / 64;
        $left = ($big - $box) / 2;
        $point = fn (float $x, float $y) => [(int) round($left + $x * $unit), (int) round($left + $y * $unit)];

        $hexagon = [];
        foreach ([[32, 3], [57, 17.5], [57, 46.5], [32, 61], [7, 46.5], [7, 17.5]] as [$x, $y]) {
            array_push($hexagon, ...$point($x, $y));
        }
        imagefilledpolygon($canvas, $hexagon, $this->color($canvas, $accent));
        // The inner hexagon leaves the accent coloured border.
        $inner = [];
        foreach ([[32, 7.6], [53, 19.8], [53, 44.2], [32, 56.4], [11, 44.2], [11, 19.8]] as [$x, $y]) {
            array_push($inner, ...$point($x, $y));
        }
        imagefilledpolygon($canvas, $inner, $this->color($canvas, '#1f1f1f'));

        // The letter H in a lighter accent.
        $light = $this->color($canvas, $this->lighter($accent));
        $stroke = 5.5 * $unit / 2;
        foreach ([[22, 19, 22, 45], [42, 19, 42, 45], [22, 32, 42, 32]] as [$x1, $y1, $x2, $y2]) {
            [$ax, $ay] = $point($x1, $y1);
            [$bx, $by] = $point($x2, $y2);
            imagefilledrectangle($canvas, (int) (min($ax, $bx) - $stroke), (int) (min($ay, $by) - $stroke), (int) (max($ax, $bx) + $stroke), (int) (max($ay, $by) + $stroke), $light);
        }

        imagecopyresampled($image, $canvas, 0, 0, 0, 0, $size, $size, $big, $big);
        imagedestroy($canvas);

        return $image;
    }

    /**
     * A square image, filled with $background or transparent.
     */
    private function canvas(int $size, ?string $background): \GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, $background !== null);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $background === null ? imagecolorallocatealpha($image, 0, 0, 0, 127) : $this->color($image, $background));

        return $image;
    }

    private function color(\GdImage $image, string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocate($image, $r, $g, $b);
    }

    private function lighter(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', ...array_map(fn (int $c) => (int) round($c + (255 - $c) * 0.2), [$r, $g, $b]));
    }

    private function ensureDirectory(): void
    {
        if (!is_dir(self::directory())) {
            mkdir(self::directory(), 0755, true);
        }
    }
}
