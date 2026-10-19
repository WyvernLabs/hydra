<?php

namespace Pterodactyl\Services\Hydra\Markdown;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;
use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;

/**
 * Hydra: turns ||text|| into a spoiler.
 */
final class SpoilerDelimiterProcessor implements DelimiterProcessorInterface
{
    public function getOpeningCharacter(): string
    {
        return '|';
    }

    public function getClosingCharacter(): string
    {
        return '|';
    }

    public function getMinLength(): int
    {
        return 2;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        return $opener->getLength() >= 2 && $closer->getLength() >= 2 ? 2 : 0;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $spoiler = new Spoiler();

        $node = $opener->next();
        while ($node !== null && $node !== $closer) {
            $next = $node->next();
            $spoiler->appendChild($node);
            $node = $next;
        }

        $opener->insertAfter($spoiler);
    }
}
