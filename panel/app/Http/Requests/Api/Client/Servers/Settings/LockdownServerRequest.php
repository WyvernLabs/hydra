<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Settings;

use Pterodactyl\Models\Server;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Hydra: only the owner and admins with "servers.lockdown" may change the
 * lockdown, subusers never, whatever permissions they have. Nobody while
 * the lockdown addon is off.
 */
class LockdownServerRequest extends ClientApiRequest
{
    public function authorize(): bool
    {
        $server = $this->route()->parameter('server');

        return $server instanceof Server
            && config('hydra.addons.lockdown.enabled', false)
            && ($this->user()->id === $server->owner_id || $this->user()->canUseLockedDownServers());
    }

    public function rules(): array
    {
        return [
            'lockdown' => 'required|boolean',
        ];
    }
}
