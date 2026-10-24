<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Support\Str;
use Pterodactyl\Models\Egg;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: custom backgrounds for the login page, the dashboard and the server
 * pages of each egg. Every background can be blurred and darkened; without an
 * image the hexagon pattern is used.
 *
 * Login and dashboard are stored as settings ("settings::hydra:backgrounds:*"),
 * eggs keep theirs in the "hydra_background" column. An egg without its own
 * background uses the dashboard background.
 *
 * Eggs also have a banner for the server dashboard and a picture for the
 * server list (EGG_IMAGES), all set under Design > Eggs.
 */
class BackgroundService
{
    public const SLOTS = ['login', 'dashboard'];

    /**
     * Pictures of an egg besides its background, with their column.
     */
    public const EGG_IMAGES = ['banner' => 'hydra_banner', 'list' => 'hydra_list_image'];

    public const MAX_BLUR = 30;

    public const MAX_DIM = 90;

    public const MAX_SIZE_KB = 20 * 1024;

    public const MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public static function directory(): string
    {
        return storage_path('app/hydra/backgrounds');
    }

    /**
     * Validation rules for a background form with the given field prefix,
     * e.g. "bg_login" for "bg_login_image", "bg_login_blur" and "bg_login_dim".
     */
    public static function rules(string $prefix): array
    {
        return [
            "{$prefix}_image" => ['nullable', 'file', 'max:' . self::MAX_SIZE_KB, 'mimes:png,jpg,jpeg,gif,webp', function (string $attribute, mixed $value, \Closure $fail) {
                $info = $value instanceof UploadedFile ? @getimagesize($value->getRealPath()) : false;
                if (!$info || !array_key_exists($info['mime'], self::MIME_TYPES)) {
                    $fail(trans('exceptions.profile.invalid_image'));
                }
            }],
            "{$prefix}_blur" => 'nullable|integer|min:0|max:' . self::MAX_BLUR,
            "{$prefix}_dim" => 'nullable|integer|min:0|max:' . self::MAX_DIM,
        ];
    }

    /**
     * Absolute path of an uploaded background, or null.
     */
    public static function path(?string $file): ?string
    {
        if (empty($file) || basename($file) !== $file) {
            return null;
        }

        $path = self::directory() . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * Background of the login page or the dashboard.
     *
     * @return array{image: string|null, blur: int, dim: int}
     */
    public function forSlot(string $slot): array
    {
        return self::format([
            'image' => config("hydra.backgrounds.$slot.image"),
            'blur' => config("hydra.backgrounds.$slot.blur"),
            'dim' => config("hydra.backgrounds.$slot.dim"),
        ]);
    }

    /**
     * Background of the server pages of an egg, or null when the egg uses
     * the dashboard background.
     *
     * @return array{image: string|null, blur: int, dim: int}|null
     */
    public function forEgg(?Egg $egg): ?array
    {
        return $egg && is_array($egg->hydra_background) ? self::format($egg->hydra_background) : null;
    }

    /**
     * Saves the background of the login page or the dashboard.
     *
     * @throws DisplayException
     */
    public function saveSlot(string $slot, ?UploadedFile $file, int $blur, int $dim): void
    {
        if ($file) {
            $old = config("hydra.backgrounds.$slot.image");
            $this->set("$slot:image", $this->store($slot, $file));
            $this->delete($old);
        }

        $this->set("$slot:blur", (string) self::clamp($blur, self::MAX_BLUR));
        $this->set("$slot:dim", (string) self::clamp($dim, self::MAX_DIM));
    }

    /**
     * Back to the hexagon pattern without blur or darkening.
     */
    public function resetSlot(string $slot): void
    {
        $this->delete(config("hydra.backgrounds.$slot.image"));

        foreach (['image', 'blur', 'dim'] as $key) {
            $this->settings->forget("settings::hydra:backgrounds:$slot:$key");
            config()->set("hydra.backgrounds.$slot.$key", null);
        }
    }

    /**
     * Saves the background of an egg's server pages.
     *
     * @throws DisplayException
     */
    public function saveEgg(Egg $egg, ?UploadedFile $file, int $blur, int $dim): void
    {
        $current = is_array($egg->hydra_background) ? $egg->hydra_background : [];
        $image = $current['image'] ?? null;

        if ($file) {
            $this->delete($image);
            $image = $this->store("egg-$egg->id", $file);
        }

        $egg->forceFill(['hydra_background' => [
            'image' => $image,
            'blur' => self::clamp($blur, self::MAX_BLUR),
            'dim' => self::clamp($dim, self::MAX_DIM),
        ]])->saveOrFail();
    }

    /**
     * The egg uses the dashboard background again.
     */
    public function resetEgg(Egg $egg): void
    {
        $this->delete($egg->hydra_background['image'] ?? null);
        $egg->forceFill(['hydra_background' => null])->saveOrFail();
    }

    /**
     * URL of the banner on the server dashboard of an egg, or null.
     */
    public function bannerForEgg(?Egg $egg): ?string
    {
        return $this->imageForEgg($egg, 'banner');
    }

    /**
     * URL of a picture of an egg ("banner" or "list"), or null.
     */
    public function imageForEgg(?Egg $egg, string $type): ?string
    {
        $file = $egg?->getAttribute(self::EGG_IMAGES[$type]);

        return self::path($file) ? '/backgrounds/' . $file : null;
    }

    /**
     * @throws DisplayException
     */
    public function saveEggImage(Egg $egg, string $type, UploadedFile $file): void
    {
        $column = self::EGG_IMAGES[$type];
        $this->delete($egg->getAttribute($column));
        $egg->forceFill([$column => $this->store("$type-egg-$egg->id", $file)])->saveOrFail();
    }

    public function resetEggImage(Egg $egg, string $type): void
    {
        $column = self::EGG_IMAGES[$type];
        $this->delete($egg->getAttribute($column));
        $egg->forceFill([$column => null])->saveOrFail();
    }

    /**
     * @throws DisplayException
     */
    private function store(string $prefix, UploadedFile $file): string
    {
        $info = @getimagesize($file->getRealPath());
        $extension = $info ? (self::MIME_TYPES[$info['mime']] ?? null) : null;
        if (is_null($extension)) {
            throw new DisplayException(trans('exceptions.profile.invalid_image'));
        }

        if (!is_dir(self::directory())) {
            mkdir(self::directory(), 0755, true);
        }

        $name = sprintf('%s-%s.%s', $prefix, Str::lower(Str::random(12)), $extension);
        $file->move(self::directory(), $name);

        return $name;
    }

    private function delete(?string $file): void
    {
        if ($path = self::path($file)) {
            @unlink($path);
        }
    }

    private function set(string $key, string $value): void
    {
        $this->settings->set("settings::hydra:backgrounds:$key", $value);
        config()->set('hydra.backgrounds.' . str_replace(':', '.', $key), $value);
    }

    /**
     * @return array{image: string|null, blur: int, dim: int}
     */
    private static function format(array $background): array
    {
        $file = $background['image'] ?? null;

        return [
            'image' => self::path($file) ? '/backgrounds/' . $file : null,
            'blur' => self::clamp((int) ($background['blur'] ?? 0), self::MAX_BLUR),
            'dim' => self::clamp((int) ($background['dim'] ?? 0), self::MAX_DIM),
        ];
    }

    private static function clamp(int $value, int $max): int
    {
        return max(0, min($max, $value));
    }
}
