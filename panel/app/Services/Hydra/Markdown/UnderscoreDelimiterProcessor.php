<?php

namespace Pterodactyl\Services\Hydra\Markdown;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;

/**
 * Hydra: underscores work like on Discord, _text_ is italic and __text__ is
 * underlined (instead of bold as in standard Markdown).
 */
final class UnderscoreDelimiterProcessor implements DelimiterProcessorInterface
{
    public function getOpeningCharacter(): string
    {
        return '_';
    }

    public function getClosingCharacter(): string
    {
        return '_';
    }

    public function getMinLength(): int
    {
        return 1;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        return $opener->getLength() >= 2 && $closer->getLength() >= 2 ? 2 : 1;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $wrapper = $delimiterUse === 2 ? new Underline() : new Emphasis('_');

        $node = $opener->next();
        while ($node !== null && $node !== $closer) {
            $next = $node->next();
            $wrapper->appendChild($node);
            $node = $next;
        }

        $opener->insertAfter($wrapper);
    }
}
