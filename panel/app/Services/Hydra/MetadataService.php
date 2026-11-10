<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: meta tags of the panel (Design > Metadata). They decide how a link
 * to the panel looks when it is posted in Discord, WhatsApp, X, … and what
 * search engines show.
 *
 * The preview image is stored like the logo (BrandingService, type "meta").
 */
class MetadataService
{
    public const MAX_TITLE = 70;

    public const MAX_DESCRIPTION = 300;

    public const MAX_KEYWORDS = 255;

    public function __construct(private SettingsRepositoryInterface $settings, private BrandingService $branding)
    {
    }

    /**
     * @return array{title: string, description: string, keywords: string, color: string|null, large_image: bool, indexable: bool}
     */
    public function values(): array
    {
        $color = config('hydra.meta.color');

        return [
            'title' => (string) config('hydra.meta.title', ''),
            'description' => (string) config('hydra.meta.description', ''),
            'keywords' => (string) config('hydra.meta.keywords', ''),
            'color' => BrandingService::isValidColor($color) ? strtolower($color) : null,
            'large_image' => filter_var(config('hydra.meta.large_image', true), FILTER_VALIDATE_BOOLEAN),
            'indexable' => filter_var(config('hydra.meta.indexable', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public function save(array $values): void
    {
        $values = [
            'title' => mb_substr(trim((string) ($values['title'] ?? '')), 0, self::MAX_TITLE),
            'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($values['description'] ?? ''))), 0, self::MAX_DESCRIPTION),
            'keywords' => mb_substr(trim((string) ($values['keywords'] ?? '')), 0, self::MAX_KEYWORDS),
            'color' => BrandingService::isValidColor($values['color'] ?? null) ? strtolower($values['color']) : '',
            'large_image' => !empty($values['large_image']),
            'indexable' => !empty($values['indexable']),
        ];

        foreach ($values as $key => $value) {
            $empty = $value === '' || ($key === 'large_image' && $value === true) || ($key === 'indexable' && $value === false);
            if ($empty) {
                $this->settings->forget("settings::hydra:meta:$key");
            } else {
                $this->settings->set("settings::hydra:meta:$key", is_bool($value) ? ($value ? 'true' : 'false') : $value);
            }

            config()->set("hydra.meta.$key", $value === '' ? null : $value);
        }
    }

    /**
     * Everything the head of a page prints, with the fallbacks filled in.
     */
    public function tags(): array
    {
        $values = $this->values();
        $name = (string) config('app.name', 'Hydra');
        $image = $this->branding->path('meta') ? url($this->imageUrl()) : null;

        return [
            'site_name' => $name,
            'title' => $values['title'] !== '' ? $values['title'] : $name,
            'description' => $values['description'],
            'keywords' => $values['keywords'],
            'color' => $values['color'] ?? $this->branding->accent(),
            'image' => $image,
            'card' => $image && $values['large_image'] ? 'summary_large_image' : 'summary',
            'indexable' => $values['indexable'],
        ];
    }

    public function imageUrl(): ?string
    {
        $path = $this->branding->path('meta');

        return $path ? sprintf('/branding/meta?v=%s', substr(md5(basename($path)), 0, 8)) : null;
    }
}
