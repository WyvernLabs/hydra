<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Startup;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Hydra: the startup command of the server (addon "Startup Command"). Null
 * goes back to the one of the egg.
 */
class UpdateStartupCommandRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'startup' => ['present', 'nullable', 'string', 'max:4096', 'not_regex:/[\r\n]/'],
        ];
    }
}
