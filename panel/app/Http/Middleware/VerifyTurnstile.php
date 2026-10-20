<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Events\Auth\FailedCaptcha;
use Illuminate\Contracts\Events\Dispatcher;
use Pterodactyl\Services\Hydra\Addons\TurnstileAddon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Hydra: checks the Cloudflare Turnstile token of a form when the Turnstile
 * addon protects it, e.g. "captcha:login".
 */
class VerifyTurnstile
{
    public function __construct(private Dispatcher $dispatcher, private TurnstileAddon $turnstile)
    {
    }

    public function handle(Request $request, \Closure $next, string $form): mixed
    {
        if (!$this->turnstile->protects($form)) {
            return $next($request);
        }

        if ($this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return $next($request);
        }

        $this->dispatcher->dispatch(new FailedCaptcha($request->ip() ?? '', $request->getHost()));

        throw new HttpException(Response::HTTP_BAD_REQUEST, trans('auth.captcha_failed'));
    }
}
