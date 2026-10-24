<?php

namespace Pterodactyl\Http\Middleware\Hydra;

use Illuminate\Http\Request;
use Pterodactyl\Services\Hydra\Addons\AddonManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: routes of an addon only exist while it is enabled, e.g.
 * "addon:tickets".
 */
class RequireAddon
{
    public function handle(Request $request, \Closure $next, string $addon): mixed
    {
        if (!AddonManager::find($addon)?->enabled()) {
            throw new NotFoundHttpException();
        }

        return $next($request);
    }
}
