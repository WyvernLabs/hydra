<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Files;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class CompressFilesRequest extends ClientApiRequest
{
    /**
     * Checks that the authenticated user is allowed to create archives for this server.
     */
    public function permission(): string
    {
        return Permission::ACTION_FILE_ARCHIVE;
    }

    public function rules(): array
    {
        return [
            'root' => 'sometimes|nullable|string',
            'files' => 'required|array',
            'files.*' => 'string',
            // Hydra: id the daemon reports the progress under, picked by the browser.
            'progress' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
        ];
    }
}
