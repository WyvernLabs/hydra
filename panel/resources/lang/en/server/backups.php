<?php

/*
 * Hydra: backup space and backups on SFTP servers.
 */
return [
    'disabled' => 'This server has no space for backups.',
    'full' => 'The backup space of this server is full. Delete older backups first.',
    'no_sftp' => 'No own SFTP server is set up for backups.',
    'credentials_required' => 'Enter a password or a private key.',
    'sftp_error' => 'SFTP server: :message',
    'sftp_in_use' => 'Backups are still stored on this SFTP server. Delete them first.',
    'sftp_file_missing' => 'This file is no longer on the SFTP server.',
    'sftp_file_imported' => 'This file is already in the list of backups.',
];
