<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Files;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Hydra: moves entries of the trash back into the server.
 */
class RestoreTrashRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_CREATE;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'string|regex:/^[a-f0-9]{32}$/',
        ];
    }
}
