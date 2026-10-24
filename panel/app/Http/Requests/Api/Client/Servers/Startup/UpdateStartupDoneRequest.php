<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Startup;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Hydra: console messages that mark the server as running. Null uses those
 * of the egg again.
 */
class UpdateStartupDoneRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'done' => 'present|nullable|array|max:5',
            'done.*' => 'required|string|max:255',
        ];
    }
}
