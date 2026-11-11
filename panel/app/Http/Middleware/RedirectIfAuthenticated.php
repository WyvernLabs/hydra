<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Auth\AuthManager;

class RedirectIfAuthenticated
{
    /**
     * RedirectIfAuthenticated constructor.
     */
    public function __construct(private AuthManager $authManager)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next, ?string $guard = null): mixed
    {
        if ($this->authManager->guard($guard)->check()) {
            // Hydra: the designer shows the login page in its live preview.
            if (
                $request->query('hydra_designer') === '1'
                && $request->isMethod('GET')
                && $this->authManager->guard($guard)->user()?->hasAdminPermission('design.view')
            ) {
                return $next($request);
            }

            return redirect()->route('index');
        }

        return $next($request);
    }
}
