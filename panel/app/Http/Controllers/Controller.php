<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Hydra: stops the request unless the user has the given admin permission.
     */
    protected function requireAdminPermission(string $permission): void
    {
        if (!request()->user()?->hasAdminPermission($permission)) {
            throw new AccessDeniedHttpException(__('admin/roles.denied'));
        }
    }
}
