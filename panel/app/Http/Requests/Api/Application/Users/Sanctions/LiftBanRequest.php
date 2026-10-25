<?php

namespace Pterodactyl\Http\Requests\Api\Application\Users\Sanctions;

use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Hydra: lifting a ban; needs the admin permission "users.ban".
 */
class LiftBanRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_USERS;

    protected int $permission = AdminAcl::WRITE;
}
