<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Files;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Hydra: uploading the log to mclo.gs reads the file, so it needs the
 * permission to read file contents.
 */
class UploadLogRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_READ_CONTENT;
    }
}
