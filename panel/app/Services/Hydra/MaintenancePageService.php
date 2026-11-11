<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

/**
 * Hydra: the "503 Service Unavailable" page (Design > Maintenance page). It is
 * shown to everyone while the panel updates itself and for any other 503.
 *
 * During an update the framework is not started at all: "artisan down
 * --render=errors::503" renders resources/views/errors/503.blade.php once
 * into storage/framework/down, and public/index.php prints that copy. The
 * page therefore has to be completely self-contained: styles inline, logo
 * and favicon embedded, and the background lies in public/hydra-maintenance
 * so the web server delivers it without PHP. After composer has installed
 * the new version the installer renders the page again
 * (hydra:maintenance:render), so it always shows the newest design.
 */
class MaintenancePageService
{
    use AvailableLanguages;

    public const MAX_TITLE = 120;

    public const MAX_DESCRIPTION = 1000;

    /**
     * Uploaded logos and favicons up to this size are embedded into the page;
     * larger ones fall back to the bundled Hydra files.
     */
    public const MAX_EMBED = 256 * 1024;

    /**
     * Folder inside public/ for the background. The installer leaves it alone
     * when it synchronizes the program files.
     */
    public const PUBLIC_FOLDER = 'hydra-maintenance';

    /**
     * Requests that keep working in maintenance mode: the admin area follows
     * the progress of the update with it.
     */
    public const EXCEPT = ['admin/settings/updates/status'];

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private BrandingService $branding,
        private SurfaceService $surfaces,
    ) {
    }

    public static function directory(): string
    {
        return public_path(self::PUBLIC_FOLDER);
    }

    /**
     * The saved values; texts per language, empty = the default text.
     *
     * @return array{title: array<string, string>, description: array<string, string>, logo: bool, image: string|null, blur: int, dim: int}
     */
    public function values(): array
    {
        $texts = config('hydra.maintenance.texts');
        if (is_string($texts)) {
            $texts = json_decode($texts, true);
        }
        $texts = is_array($texts) ? $texts : [];

        return [
            'title' => self::strings($texts['title'] ?? []),
            'description' => self::strings($texts['description'] ?? []),
            'logo' => filter_var(config('hydra.maintenance.logo', true) ?? true, FILTER_VALIDATE_BOOLEAN),
            'image' => $this->imageUrl(),
            'blur' => self::clamp(config('hydra.maintenance.blur'), BackgroundService::MAX_BLUR),
            'dim' => self::clamp(config('hydra.maintenance.dim'), BackgroundService::MAX_DIM),
        ];
    }

    /**
     * @param array{title?: array, description?: array, logo?: bool, blur?: int, dim?: int} $values
     */
    public function save(array $values): void
    {
        $languages = array_keys($this->getAvailableLanguages());
        $texts = ['title' => [], 'description' => []];
        foreach ($languages as $code) {
            $title = trim(preg_replace('/\s+/u', ' ', (string) ($values['title'][$code] ?? '')));
            $description = trim(str_replace("\r\n", "\n", (string) ($values['description'][$code] ?? '')));
            if ($title !== '') {
                $texts['title'][$code] = mb_substr($title, 0, self::MAX_TITLE);
            }
            if ($description !== '') {
                $texts['description'][$code] = mb_substr($description, 0, self::MAX_DESCRIPTION);
            }
        }

        if (empty($texts['title']) && empty($texts['description'])) {
            $this->forget('texts');
        } else {
            $this->set('texts', json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $logo = (bool) ($values['logo'] ?? true);
        $logo ? $this->forget('logo') : $this->set('logo', 'false');

        foreach (['blur' => BackgroundService::MAX_BLUR, 'dim' => BackgroundService::MAX_DIM] as $key => $max) {
            $value = self::clamp($values[$key] ?? 0, $max);
            $value === 0 ? $this->forget($key) : $this->set($key, (string) $value);
        }

        $this->refreshDownFile();
    }

    /**
     * Stores a new background and removes the previous one.
     *
     * @throws DisplayException
     */
    public function storeImage(UploadedFile $file): void
    {
        $info = @getimagesize($file->getRealPath());
        $extension = $info ? (BackgroundService::MIME_TYPES[$info['mime']] ?? null) : null;
        if (is_null($extension)) {
            throw new DisplayException(trans('exceptions.profile.invalid_image'));
        }

        if (!is_dir(self::directory())) {
            mkdir(self::directory(), 0755, true);
        }

        $old = $this->imagePath();
        $name = sprintf('background-%s.%s', Str::lower(Str::random(16)), $extension);
        $file->move(self::directory(), $name);
        $this->set('image', $name);
        if ($old) {
            @unlink($old);
        }

        $this->refreshDownFile();
    }

    /**
     * Back to the hexagon pattern without blur or darkening.
     */
    public function resetImage(): void
    {
        if ($path = $this->imagePath()) {
            @unlink($path);
        }

        foreach (['image', 'blur', 'dim'] as $key) {
            $this->forget($key);
        }

        $this->refreshDownFile();
    }

    public function imageUrl(): ?string
    {
        $path = $this->imagePath();

        return $path ? '/' . self::PUBLIC_FOLDER . '/' . basename($path) : null;
    }

    /**
     * The default texts of a language (resources/lang/<code>/maintenance.php).
     */
    public static function defaults(string $code): array
    {
        return [
            'title' => (string) trans('maintenance.title', [], $code),
            'description' => (string) trans('maintenance.description', [], $code),
            'status' => (string) trans('maintenance.status', [], $code),
        ];
    }

    /**
     * Everything resources/views/errors/503.blade.php shows.
     */
    public function pageData(?string $locale = null): array
    {
        $values = $this->values();
        $texts = [];
        foreach (array_keys($this->getAvailableLanguages()) as $code) {
            $defaults = self::defaults($code);
            $texts[$code] = [
                'title' => ($values['title'][$code] ?? '') ?: $defaults['title'],
                'description' => ($values['description'][$code] ?? '') ?: $defaults['description'],
                'status' => $defaults['status'],
            ];
        }

        $locale = collect([$locale, app()->getLocale(), config('app.locale'), 'en'])
            ->first(fn ($code) => is_string($code) && isset($texts[$code])) ?? array_key_first($texts);

        return [
            'locale' => $locale,
            'texts' => $texts,
            'text' => $texts[$locale] ?? self::defaults('en'),
            'name' => (string) config('app.name', 'Hydra'),
            'css' => $this->branding->cssVariables() . $this->surfaces->cssVariables(),
            'logo' => $values['logo'] ? $this->embed('logo', BrandingService::DEFAULT_LOGO) : null,
            'favicon' => $this->embed('favicon', BrandingService::DEFAULT_FAVICON),
            'protect' => $this->branding->protectsImages(),
            'background' => ['image' => $values['image'], 'blur' => $values['blur'], 'dim' => $values['dim']],
        ];
    }

    /**
     * Minimal data for the page if the settings cannot be read at all.
     */
    public static function fallback(): array
    {
        $locale = app()->getLocale() ?: 'en';
        $text = self::defaults($locale);

        return [
            'locale' => $locale,
            'texts' => [$locale => $text],
            'text' => $text,
            'name' => (string) config('app.name', 'Hydra'),
            'css' => '',
            'logo' => BrandingService::DEFAULT_LOGO,
            'favicon' => BrandingService::DEFAULT_FAVICON,
            'protect' => true,
            'background' => ['image' => null, 'blur' => 0, 'dim' => 0],
        ];
    }

    public function render(): string
    {
        return view('errors.503')->render();
    }

    /**
     * Renders the page again into an active maintenance mode, so a running
     * update shows the current design and lets the update status through.
     */
    public function refreshDownFile(): bool
    {
        $mode = app()->maintenanceMode();
        if (!$mode->active()) {
            return false;
        }

        $data = $mode->data();
        $data['template'] = $this->render();
        $data['except'] = app(PreventRequestsDuringMaintenance::class)->getExcludedPaths();
        $data['retry'] ??= 30;
        $mode->activate($data);

        return true;
    }

    private function imagePath(): ?string
    {
        $file = config('hydra.maintenance.image');
        if (!is_string($file) || $file === '' || basename($file) !== $file) {
            return null;
        }

        $path = self::directory() . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * An uploaded logo or favicon as data URI, the bundled file otherwise
     * (those are static files the web server delivers without PHP).
     */
    private function embed(string $type, string $default): string
    {
        $path = $this->branding->path($type);
        $mime = $path ? (BrandingService::MIME_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null) : null;
        if (!$path || !$mime || filesize($path) > self::MAX_EMBED) {
            return $default;
        }

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private function set(string $key, string $value): void
    {
        $this->settings->set("settings::hydra:maintenance:$key", $value);
        config()->set("hydra.maintenance.$key", $value);
    }

    private function forget(string $key): void
    {
        $this->settings->forget("settings::hydra:maintenance:$key");
        config()->set("hydra.maintenance.$key", null);
    }

    /**
     * @return array<string, string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_filter(array_map(fn ($value) => is_string($value) ? $value : '', $values), fn ($value) => $value !== '') : [];
    }

    private static function clamp(mixed $value, int $max): int
    {
        return is_numeric($value) ? max(0, min($max, (int) $value)) : 0;
    }
}
