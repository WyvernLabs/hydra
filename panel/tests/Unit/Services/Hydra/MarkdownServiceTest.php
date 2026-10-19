<?php

namespace Pterodactyl\Tests\Unit\Services\Hydra;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\Hydra\Markdown\MarkdownService;

class MarkdownServiceTest extends TestCase
{
    private function render(string $markdown): string
    {
        return trim((new MarkdownService())->render($markdown));
    }

    public function testDiscordFormatting(): void
    {
        $html = $this->render('**bold** *italic* _italic_ __underline__ ~~strike~~ ||secret|| `code` snake_case_name');

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em> <em>italic</em>', $html);
        $this->assertStringContainsString('<u>underline</u>', $html);
        $this->assertStringContainsString('<del>strike</del>', $html);
        $this->assertStringContainsString('<span class="md-spoiler" tabindex="0">secret</span>', $html);
        $this->assertStringContainsString('<code>code</code>', $html);
        $this->assertStringContainsString('snake_case_name', $html);
    }

    public function testBlocks(): void
    {
        $html = $this->render("# Title\nline one\nline two\n-# small print\n> quote\n- item");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString("line one<br />\nline two", $html);
        $this->assertStringContainsString('<p class="md-subtext">small print</p>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<li>item</li>', $html);
    }

    public function testUnsafeContentIsRemoved(): void
    {
        $html = $this->render("<script>alert(1)</script>\n\n[click](javascript:alert(1)) <img src=x onerror=alert(1)>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function testEmptyContentRendersNothing(): void
    {
        $this->assertSame('', $this->render('   '));
    }
}
