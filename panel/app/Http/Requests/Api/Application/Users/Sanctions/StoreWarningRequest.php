<?php

namespace Pterodactyl\Http\Requests\Api\Application\Users\Sanctions;

use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Hydra: warning a user account; needs the admin permission "users.warn".
 */
class StoreWarningRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_USERS;

    protected int $permission = AdminAcl::WRITE;

    public function rules(): array
    {
        return ['reason' => 'required|string|max:2000'];
    }
}
