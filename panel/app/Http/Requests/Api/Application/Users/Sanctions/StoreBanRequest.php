<?php

namespace Pterodactyl\Http\Requests\Api\Application\Users\Sanctions;

use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Hydra: banning a user account; needs the admin permission "users.ban".
 * Without days and without "permanent" the days of the template apply,
 * without a template the ban is permanent.
 */
class StoreBanRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_USERS;

    protected int $permission = AdminAcl::WRITE;

    public function rules(): array
    {
        return [
            'template' => 'nullable|integer|exists:ban_templates,id',
            'reason' => 'required_without:template|nullable|string|max:2000',
            'duration_days' => 'nullable|integer|between:1,3650',
            'permanent' => 'sometimes|boolean',
        ];
    }
}
