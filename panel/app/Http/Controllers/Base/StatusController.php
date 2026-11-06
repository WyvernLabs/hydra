<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Addons\StatusAddon;
use Pterodactyl\Services\Hydra\Status\StatusPageService;

/**
 * Hydra: the public status page (addon "status").
 */
class StatusController extends Controller
{
    public function __invoke(StatusAddon $addon, StatusPageService $service): View
    {
        return view('templates.status', $service->page() + [
            'title' => $addon->get('title') ?: __('client/status.title'),
            'description' => $addon->get('description'),
        ]);
    }
}
