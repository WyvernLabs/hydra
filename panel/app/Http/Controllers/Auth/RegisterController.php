<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Rules\Username;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Hydra\RegistrationService;
use Pterodactyl\Services\Hydra\Addons\RegistrationAddon;

/**
 * Hydra: visitors create an account themselves (Registration addon). The
 * account is logged in right away and no "account created" email is sent.
 */
class RegisterController extends AbstractLoginController
{
    public function __construct(private RegistrationAddon $addon, private RegistrationService $registration)
    {
        parent::__construct();
    }

    /**
     * Step 1: mail a code to the address.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function code(Request $request): Response
    {
        $this->ensureEnabled();

        $data = $request->validate(['email' => 'required|email:strict|max:191']);
        $this->registration->sendCode($data['email']);

        return response()->noContent();
    }

    /**
     * Step 2: confirm the address with the code.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function verify(Request $request): Response
    {
        $this->ensureEnabled();

        $data = $request->validate([
            'email' => 'required|email:strict|max:191',
            'code' => 'required|string|digits:6',
        ]);
        $this->registration->verifyCode($request->session(), $data['email'], $data['code']);

        return response()->noContent();
    }

    /**
     * Step 3: create the account and log it in.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Throwable
     */
    public function register(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $data = $request->validate([
            'name_first' => 'required|string|between:1,191',
            'name_last' => 'required|string|between:1,191',
            'username' => ['required', 'string', 'between:3,191', new Username(), 'unique:users,username'],
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $this->registration->register($request->session(), $data);

        return $this->sendLoginResponse($user, $request);
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->addon->enabled(), Response::HTTP_NOT_FOUND);
    }
}
