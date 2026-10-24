<?php

namespace Pterodactyl\Exceptions\Service\Backup;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: the node could not use an SFTP server for backups. The message comes
 * from the node and names the problem (login, host key, folder, …).
 */
class SftpBackupException extends DisplayException
{
    public function __construct(string $message)
    {
        parent::__construct(__('server/backups.sftp_error', ['message' => preg_replace('/^SFTP: /', '', $message)]));
    }
}
