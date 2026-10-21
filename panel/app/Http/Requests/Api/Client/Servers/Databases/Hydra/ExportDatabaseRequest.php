<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Hydra;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ExportDatabaseRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_DATABASE_EXPORT;
    }
}
