<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\NewsService;

class BaseController extends Controller
{
    public function __construct(private NewsService $news)
    {
    }

    /**
     * Hydra: the admin overview with version, build and the news of every release.
     */
    public function index(): View
    {
        return view('admin.index', [
            'news' => $this->news->overview(),
            'interval' => $this->news->interval(),
        ]);
    }

    /**
     * The news box, reloaded every few minutes and by the refresh button.
     */
    public function news(Request $request): JsonResponse
    {
        $news = $this->news->overview($request->boolean('refresh'));

        return new JsonResponse([
            'html' => view('partials.admin.hydra.news', ['news' => $news])->render(),
            'system' => view('partials.admin.hydra.version', ['news' => $news])->render(),
            'has_update' => $news['has_update'],
            'checked_at' => $news['checked_at'],
        ]);
    }
}
