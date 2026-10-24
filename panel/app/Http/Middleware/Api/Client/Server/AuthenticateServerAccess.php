<?php

namespace Pterodactyl\Http\Middleware\Api\Client\Server;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Exceptions\Http\Server\ServerLockedDownException;
use Pterodactyl\Exceptions\Http\Server\ServerStateConflictException;

class AuthenticateServerAccess
{
    /**
     * Routes that this middleware should not apply to if the user is an admin.
     */
    protected array $except = [
        'api:client:server.ws',
    ];

    /**
     * Hydra: areas of the client API that stay available while a server is
     * suspended, so users can fix problems or at least save their data.
     */
    public const SUSPENDED_AREAS = ['files', 'databases', 'backups'];

    /**
     * AuthenticateServerAccess constructor.
     */
    public function __construct()
    {
    }

    /**
     * Authenticate that this server exists and is not suspended or marked as installing.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = $request->user();
        $server = $request->route()->parameter('server');

        if (!$server instanceof Server) {
            throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));
        }

        // At the very least, ensure that the user trying to make this request is the
        // server owner, a subuser, or a root admin. We'll leave it up to the controllers
        // to authenticate more detailed permissions if needed.
        if ($user->id !== $server->owner_id && !$user->canAccessAllServers()) {
            // Check for subuser status.
            if (!$server->subusers->contains('user_id', $user->id)) {
                throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));
            }
        }

        // Hydra: during a lockdown everyone else only gets the server itself, so
        // the panel can tell them why they cannot use it.
        if (!$server->lockdownAllows($user) && !$request->routeIs('api:client:server.view')) {
            throw new ServerLockedDownException();
        }

        try {
            $server->validateCurrentState();
        } catch (ServerStateConflictException $exception) {
            // Still allow users to get information about their server if it is installing or
            // being transferred.
            if (!$request->routeIs('api:client:server.view') && !$this->allowedWhileSuspended($request, $server)) {
                if (($server->isSuspended() || $server->node->isUnderMaintenance()) && !$request->routeIs('api:client:server.resources')) {
                    throw $exception;
                }
                if (!$user->canAccessAllServers() || !$request->routeIs($this->except)) {
                    throw $exception;
                }
            }
        }

        $request->attributes->set('server', $server);

        return $next($request);
    }

    /**
     * Hydra: users allowed to use suspended servers keep full access, everyone
     * else keeps the files, databases and backups of a suspended server.
     */
    private function allowedWhileSuspended(Request $request, Server $server): bool
    {
        if (!$server->isSuspended() || $server->node->isUnderMaintenance() || !is_null($server->transfer)) {
            return false;
        }

        if ($request->user()->canUseSuspendedServers()) {
            return true;
        }

        $area = Str::before(Str::after($request->route()->uri(), '{server}/'), '/');

        return in_array($area, self::SUSPENDED_AREAS, true);
    }
}
