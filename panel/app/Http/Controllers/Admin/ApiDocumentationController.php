<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Api\ApiDocumentation;

/**
 * Hydra: documentation of the application and client API (Admin > API).
 */
class ApiDocumentationController extends Controller
{
    public function __invoke(ApiDocumentation $documentation): View
    {
        $apis = [];
        foreach (ApiDocumentation::APIS as $api) {
            $apis[$api] = $documentation->groups($api);
        }

        return view('admin.api.docs', [
            'apis' => $apis,
            'limits' => config('http.rate_limit'),
            'url' => rtrim((string) config('app.url'), '/'),
        ]);
    }
}
