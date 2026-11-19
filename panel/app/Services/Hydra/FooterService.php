<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: own copyright text in the footer of the client area and the public
 * pages (Designer > Metadata), one per language. Without one, "Name © year"
 * is shown. The admin area keeps its footer with the fixed credit
 * BrandingService::CREDIT, which pages for users do not show.
 *
 * Placeholders: {year} (current year) and {name} (panel name).
 * Setting: "settings::hydra:footer:texts" (JSON, language => text).
 */
class FooterService
{
    public const MAX_LENGTH = 160;

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * @return array<string, string> language => text, only languages with a text
     */
    public function texts(): array
    {
        $texts = config('hydra.footer.texts');
        if (is_string($texts)) {
            $texts = json_decode($texts, true);
        }

        return is_array($texts) ? array_filter(array_map(fn ($text) => is_string($text) ? $text : '', $texts), 'strlen') : [];
    }

    /**
     * @param array<string, ?string> $texts language => text
     */
    public function save(array $texts): void
    {
        $clean = [];
        foreach ($texts as $language => $text) {
            $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
            if ($text !== '' && is_string($language)) {
                $clean[$language] = mb_substr($text, 0, self::MAX_LENGTH);
            }
        }

        if (empty($clean)) {
            $this->settings->forget('settings::hydra:footer:texts');
            config()->set('hydra.footer.texts', null);
        } else {
            $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
            $this->settings->set('settings::hydra:footer:texts', $json);
            config()->set('hydra.footer.texts', $json);
        }
    }

    /**
     * The own text for a language with filled in placeholders, falling back to
     * the default language; null when none is set (the default footer is used).
     */
    public function text(string $locale): ?string
    {
        $texts = $this->texts();
        $text = $texts[$locale] ?? $texts[config('app.locale')] ?? null;

        return is_null($text) ? null : strtr($text, [
            '{year}' => date('Y'),
            '{name}' => trim((string) config('app.name', 'Hydra')),
        ]);
    }
}
