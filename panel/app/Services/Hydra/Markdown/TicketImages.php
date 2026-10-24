<?php

namespace Pterodactyl\Services\Hydra\Markdown;

use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;

/**
 * Hydra: tickets only show images uploaded for tickets. Every other image is
 * turned into a link, so that external images cannot track who reads a ticket.
 */
final class TicketImages
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $images = [];
        $walker = $event->getDocument()->walker();
        while ($item = $walker->next()) {
            if ($item->isEntering() && $item->getNode() instanceof Image) {
                $images[] = $item->getNode();
            }
        }

        foreach ($images as $image) {
            if (self::isAttachment($image->getUrl())) {
                continue;
            }

            $link = new Link($image->getUrl(), null, $image->getTitle());
            foreach ($image->children() as $child) {
                $link->appendChild($child);
            }
            if (is_null($link->firstChild())) {
                $link->appendChild(new Text($image->getUrl()));
            }
            $image->replaceWith($link);
        }
    }

    /**
     * Whether the address is an image uploaded for a ticket on this panel.
     */
    public static function isAttachment(string $url): bool
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($base !== '' && str_starts_with($url, $base . '/')) {
            $url = substr($url, strlen($base));
        }

        return (bool) preg_match('#^/tickets/attachments/[0-9a-f-]{36}$#', $url);
    }
}
