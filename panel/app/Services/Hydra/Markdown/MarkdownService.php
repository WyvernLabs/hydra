<?php

namespace Pterodactyl\Services\Hydra\Markdown;

use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;

/**
 * Hydra: renders Markdown the way Discord does — headings, **bold**, *italic*,
 * __underline__, ~~strike~~, ||spoiler||, `code`, code blocks, > quotes,
 * lists, links, -# subtext — plus tables. Raw HTML is removed and unsafe
 * links are blocked, so the output can be shown to every user.
 */
class MarkdownService
{
    private ?MarkdownConverter $converter = null;

    public function render(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return (string) $this->converter()->convert($markdown);
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 32,
            // Discord keeps line breaks within a paragraph.
            'renderer' => ['soft_break' => "<br />\n"],
            // Underscores are handled by UnderscoreDelimiterProcessor.
            'commonmark' => ['use_underscore' => false],
            'external_link' => [
                'internal_hosts' => [parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'],
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new ExternalLinkExtension());
        $environment->addDelimiterProcessor(new SpoilerDelimiterProcessor());
        $environment->addDelimiterProcessor(new UnderscoreDelimiterProcessor());
        // Before list items, as "-# text" would otherwise be a list.
        $environment->addBlockStartParser(new SubtextParser(), 65);
        $environment->addRenderer(Spoiler::class, new DiscordRenderer());
        $environment->addRenderer(Underline::class, new DiscordRenderer());
        $environment->addRenderer(Subtext::class, new DiscordRenderer());

        return $this->converter = new MarkdownConverter($environment);
    }
}
