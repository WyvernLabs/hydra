<?php

namespace Pterodactyl\Exceptions\Service\Backup;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: no backup can be created, because the server has no backup space,
 * it is full, or the chosen SFTP server is not set up.
 */
class BackupStorageException extends DisplayException
{
    public function __construct(string $reason)
    {
        parent::__construct(__('server/backups.' . $reason));
    }
}
