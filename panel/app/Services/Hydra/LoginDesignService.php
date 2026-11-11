<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: look of the login page and the other pages for visitors (Designer >
 * Login). Three styles:
 *
 *  - classic  the form as a card in the middle of the login background
 *  - split    one card, the form on one side and a picture on the other
 *  - side     the form on one half of the screen, the picture fills the
 *             other half and fades into the background
 *
 * "split" and "side" can be mirrored. Title, subtitle and an info box below
 * the form are set per language; empty = the default texts.
 */
class LoginDesignService
{
    use AvailableLanguages;

    public const STYLES = ['classic', 'split', 'side'];

    public const TEXTS = ['title' => 80, 'subtitle' => 160, 'info' => 500];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * @return array{style: string, mirror: bool, image: string|null, texts: array<string, array<string, string>>}
     */
    public function values(): array
    {
        $style = (string) config('hydra.login.style');
        $texts = config('hydra.login.texts');
        if (is_string($texts)) {
            $texts = json_decode($texts, true);
        }
        $texts = is_array($texts) ? $texts : [];

        $result = [];
        foreach (array_keys(self::TEXTS) as $key) {
            $result[$key] = is_array($texts[$key] ?? null)
                ? array_filter($texts[$key], fn ($value) => is_string($value) && $value !== '')
                : [];
        }

        return [
            'style' => in_array($style, self::STYLES, true) ? $style : 'classic',
            'mirror' => filter_var(config('hydra.login.mirror', false), FILTER_VALIDATE_BOOLEAN),
            'image' => $this->imageUrl(),
            'texts' => $result,
        ];
    }

    /**
     * @param array{style?: string, mirror?: bool, texts?: array} $values
     */
    public function save(array $values): void
    {
        $style = in_array($values['style'] ?? null, self::STYLES, true) ? $values['style'] : 'classic';
        $style === 'classic' ? $this->forget('style') : $this->set('style', $style);
        !empty($values['mirror']) ? $this->set('mirror', 'true') : $this->forget('mirror');

        $texts = [];
        foreach (array_keys($this->getAvailableLanguages()) as $code) {
            foreach (self::TEXTS as $key => $max) {
                $value = trim(str_replace("\r\n", "\n", (string) ($values['texts'][$key][$code] ?? '')));
                if ($key !== 'info') {
                    $value = preg_replace('/\s+/u', ' ', $value);
                }
                if ($value !== '') {
                    $texts[$key][$code] = mb_substr($value, 0, $max);
                }
            }
        }

        empty($texts) ? $this->forget('texts') : $this->set('texts', json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @throws DisplayException
     */
    public function storeImage(UploadedFile $file): void
    {
        $info = @getimagesize($file->getRealPath());
        $extension = $info ? (BackgroundService::MIME_TYPES[$info['mime']] ?? null) : null;
        if (is_null($extension)) {
            throw new DisplayException(trans('exceptions.profile.invalid_image'));
        }

        if (!is_dir(BackgroundService::directory())) {
            mkdir(BackgroundService::directory(), 0755, true);
        }

        $old = BackgroundService::path(config('hydra.login.image'));
        $name = sprintf('login-side-%s.%s', Str::lower(Str::random(12)), $extension);
        $file->move(BackgroundService::directory(), $name);
        $this->set('image', $name);
        if ($old) {
            @unlink($old);
        }
    }

    public function resetImage(): void
    {
        if ($path = BackgroundService::path(config('hydra.login.image'))) {
            @unlink($path);
        }
        $this->forget('image');
    }

    public function imageUrl(): ?string
    {
        $file = config('hydra.login.image');

        return BackgroundService::path(is_string($file) ? $file : null) ? '/backgrounds/' . $file : null;
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.login), texts in
     * the language of the visitor.
     */
    public function toFrontend(string $locale): array
    {
        $values = $this->values();
        $text = fn (string $key) => $values['texts'][$key][$locale] ?? $values['texts'][$key][config('app.locale')] ?? null;

        return [
            'style' => $values['style'],
            'mirror' => $values['mirror'],
            'image' => $values['image'],
            'title' => $text('title'),
            'subtitle' => $text('subtitle'),
            'info' => $text('info'),
        ];
    }

    private function set(string $key, string $value): void
    {
        $this->settings->set("settings::hydra:login:$key", $value);
        config()->set("hydra.login.$key", $value);
    }

    private function forget(string $key): void
    {
        $this->settings->forget("settings::hydra:login:$key");
        config()->set("hydra.login.$key", null);
    }
}
