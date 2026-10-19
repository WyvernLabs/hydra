<?php

namespace Pterodactyl\Http\Middleware\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Hydra: only lets the request through when the user has every admin
 * permission given, e.g. "admin.can:servers.view,servers.build". Alternatives
 * are separated by "|": "admin.can:users.view|servers.create" needs one of them.
 */
class RequireAdminPermission
{
    public function handle(Request $request, \Closure $next, string ...$permissions): mixed
    {
        $user = $request->user();

        foreach ($permissions as $alternatives) {
            $allowed = $user && collect(explode('|', $alternatives))
                ->contains(fn (string $permission) => $user->hasAdminPermission($permission));

            if (!$allowed) {
                throw new AccessDeniedHttpException(__('admin/roles.denied'));
            }
        }

        return $next($request);
    }
}
