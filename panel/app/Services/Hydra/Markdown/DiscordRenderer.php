<?php

namespace Pterodactyl\Services\Hydra\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;

/**
 * Hydra: HTML for spoilers, underlines and subtext.
 */
final class DiscordRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        $children = $childRenderer->renderNodes($node->children());

        return match (true) {
            $node instanceof Spoiler => new HtmlElement('span', ['class' => 'md-spoiler', 'tabindex' => '0'], $children),
            $node instanceof Underline => new HtmlElement('u', [], $children),
            default => new HtmlElement('p', ['class' => 'md-subtext'], $children),
        };
    }
}
