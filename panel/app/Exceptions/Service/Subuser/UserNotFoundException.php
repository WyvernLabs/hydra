<?php

namespace Pterodactyl\Exceptions\Service\Subuser;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: subusers are only added from existing accounts, never created.
 */
class UserNotFoundException extends DisplayException
{
}
