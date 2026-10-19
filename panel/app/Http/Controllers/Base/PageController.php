<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\PanelLink;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Markdown\MarkdownService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: pages written in the admin area, e.g. the imprint. Logged in users
 * see them inside the panel; public pages can be opened without logging in.
 */
class PageController extends Controller
{
    public function __construct(private MarkdownService $markdown)
    {
    }

    public function __invoke(Request $request, string $slug): View|RedirectResponse
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

        if ($request->user()) {
            return view('templates/base.core');
        }

        if (!$page->public) {
            return redirect()->guest(route('auth.login'));
        }

        return view('templates.page', [
            'title' => $page->localizedTitle(),
            'html' => $this->markdown->render($page->localizedContent()),
        ]);
    }
}
