<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Pterodactyl\Models\PanelLink;
use Pterodactyl\Services\Hydra\Markdown\MarkdownService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: pages written in the admin area, rendered from Markdown.
 */
class PageController extends ClientApiController
{
    public function __construct(private MarkdownService $markdown)
    {
        parent::__construct();
    }

    public function view(string $slug): array
    {
        /** @var PanelLink|null $page */
        $page = PanelLink::query()
            ->where('type', PanelLink::TYPE_PAGE)
            ->where('enabled', true)
            ->where('slug', $slug)
            ->first();

        if (is_null($page)) {
            throw new NotFoundHttpException();
        }

        return [
            'object' => 'panel_page',
            'attributes' => [
                'slug' => $page->slug,
                'title' => $page->localizedTitle(),
                'html' => $this->markdown->render($page->localizedContent()),
                'updated_at' => $page->updated_at->toAtomString(),
            ],
        ];
    }
}
