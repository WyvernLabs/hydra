<?php

namespace Pterodactyl\Services\Hydra\Markdown;

use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\InlineParserEngineInterface;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockContinueParserWithInlinesInterface;

/**
 * Hydra: parses "-# text" lines into subtext, like on Discord.
 */
final class SubtextParser extends AbstractBlockContinueParser implements BlockContinueParserWithInlinesInterface, BlockStartParserInterface
{
    private Subtext $block;

    public function __construct(private string $content = '')
    {
        $this->block = new Subtext();
    }

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || !preg_match('/^-#[ \t]+(.*)$/', $cursor->getRemainder(), $match)) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new self($match[1]))->at($cursor);
    }

    public function getBlock(): Subtext
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        return BlockContinue::none();
    }

    public function parseInlines(InlineParserEngineInterface $inlineParser): void
    {
        $inlineParser->parse($this->content, $this->block);
    }
}
