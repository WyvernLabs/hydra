<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Files;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Hydra: removes entries of the trash for good.
 */
class DeleteTrashRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_DELETE;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required_without:all|array|max:500',
            'ids.*' => 'string|regex:/^[a-f0-9]{32}$/',
            'all' => 'sometimes|boolean',
        ];
    }
}
