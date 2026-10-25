<?php

namespace Pterodactyl\Http\Requests\Api\Application\Users\Sanctions;

use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Hydra: reading warnings, bans and ban templates.
 */
class GetUserSanctionsRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_USERS;

    protected int $permission = AdminAcl::READ;
}
