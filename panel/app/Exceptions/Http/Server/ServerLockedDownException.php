<?php

namespace Pterodactyl\Exceptions\Http\Server;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Hydra: thrown for users who may not use a server while it is in lockdown.
 */
class ServerLockedDownException extends AccessDeniedHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(trans('exceptions.server_locked_down'), $previous);
    }
}
