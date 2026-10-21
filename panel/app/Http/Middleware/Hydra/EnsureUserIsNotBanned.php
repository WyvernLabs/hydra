<?php

namespace Pterodactyl\Http\Middleware\Hydra;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hydra: banned users are logged out with their next request, through the web
 * session as well as API keys.
 */
class EnsureUserIsNotBanned
{
    public function __construct(private AuthManager $auth)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user || !($ban = $user->activeBan())) {
            return $next($request);
        }

        if ($request->hasSession()) {
            $this->auth->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse(['errors' => [[
                'code' => 'AccountBanned',
                'status' => '403',
                'detail' => self::message($ban),
            ]]], Response::HTTP_FORBIDDEN);
        }

        return new RedirectResponse('/auth/login?banned=1');
    }

    /**
     * The message shown to a banned user: reason and until when.
     */
    public static function message(\Pterodactyl\Models\UserSanction $ban): string
    {
        return $ban->expires_at
            ? trans('auth.banned_until', ['reason' => $ban->reason, 'date' => $ban->expires_at->locale(app()->getLocale())->isoFormat('LLL')])
            : trans('auth.banned', ['reason' => $ban->reason]);
    }
}
