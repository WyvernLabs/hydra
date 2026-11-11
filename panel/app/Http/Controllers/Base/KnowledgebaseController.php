<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\HydraKbPost;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Services\Hydra\KnowledgebaseService;
use Pterodactyl\Services\Hydra\Addons\KnowledgebaseAddon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: the knowledgebase for users (addon "knowledgebase") under the
 * address set in the addon. Without "public" only logged in users get in.
 * Drafts are never shown here.
 */
class KnowledgebaseController extends Controller
{
    public function __construct(
        private KnowledgebaseService $service,
        private KnowledgebaseAddon $addon,
        private BackgroundService $backgrounds,
    ) {
    }

    public function __invoke(Request $request, string $knowledgebase, ?string $post = null): View|RedirectResponse
    {
        if (!$request->user() && !$this->addon->isPublic()) {
            return redirect()->guest(route('auth.login'));
        }

        $tree = $this->service->tree();
        $current = null;
        if (!is_null($post)) {
            $current = HydraKbPost::query()->where('slug', $post)->where('published', true)->with('category.parent')->first();
            if (is_null($current)) {
                throw new NotFoundHttpException();
            }
        }

        $flat = $this->service->flatPosts($tree);
        $index = $current ? array_search($current->id, array_map(fn ($p) => $p->id, $flat), true) : false;

        return view('templates.knowledgebase', [
            'tree' => $tree,
            'post' => $current,
            'html' => $current ? $this->service->render($current->content()) : null,
            'previous' => $index !== false && $index > 0 ? $flat[$index - 1] : null,
            'next' => $index !== false && $index < count($flat) - 1 ? $flat[$index + 1] : null,
            'base' => $this->addon->url(),
            'background' => $this->backgrounds->forSlot($request->user() ? 'dashboard' : 'login'),
        ]);
    }
}
