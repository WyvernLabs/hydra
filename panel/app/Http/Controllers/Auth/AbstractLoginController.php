<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Events\Failed;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Pterodactyl\Http\Middleware\Hydra\EnsureUserIsNotBanned;

abstract class AbstractLoginController extends Controller
{
    use AuthenticatesUsers;

    protected AuthManager $auth;

    /**
     * Lockout time for failed login requests.
     */
    protected int $lockoutTime;

    /**
     * After how many attempts should logins be throttled and locked.
     */
    protected int $maxLoginAttempts;

    /**
     * Where to redirect users after login / registration.
     */
    protected string $redirectTo = '/';

    /**
     * LoginController constructor.
     */
    public function __construct()
    {
        $this->lockoutTime = config('auth.lockout.time');
        $this->maxLoginAttempts = config('auth.lockout.attempts');
        $this->auth = Container::getInstance()->make(AuthManager::class);
    }

    /**
     * Get the failed login response instance.
     *
     * @return never-return
     *
     * @throws DisplayException
     */
    protected function sendFailedLoginResponse(Request $request, ?Authenticatable $user = null, ?string $message = null)
    {
        $this->incrementLoginAttempts($request);
        $this->fireFailedLoginEvent($user, [
            $this->getField($request->input('user')) => $request->input('user'),
        ]);

        if ($request->route()->named('auth.login-checkpoint')) {
            throw new DisplayException($message ?? trans('auth.two_factor.checkpoint_failed'));
        }

        throw new DisplayException(trans('auth.failed'));
    }

    /**
     * Send the response after the user was authenticated.
     */
    protected function sendLoginResponse(User $user, Request $request): JsonResponse
    {
        // Hydra: banned accounts cannot log in. Checked only after the password
        // (and 2FA), so the ban does not tell whether an account exists.
        if ($ban = $user->activeBan()) {
            $request->session()->remove('auth_confirmation_token');
            $this->fireFailedLoginEvent($user, ['username' => $user->username]);

            throw new DisplayException(EnsureUserIsNotBanned::message($ban));
        }

        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        $this->auth->guard()->login($user, true);

        Event::dispatch(new DirectLogin($user, true));

        return new JsonResponse([
            'data' => [
                'complete' => true,
                'intended' => $this->redirectPath(),
                'user' => $user->toVueObject(),
            ],
        ]);
    }

    /**
     * Hydra: users only log in with their username, never with their email.
     */
    protected function getField(?string $input = null): string
    {
        return 'username';
    }

    /**
     * Fire a failed login event.
     */
    protected function fireFailedLoginEvent(?Authenticatable $user = null, array $credentials = [])
    {
        Event::dispatch(new Failed('auth', $user, $credentials));
    }
}
