<?php

/**
 * Contains all of the translation strings for different activity log
 * events. These should be keyed by the value in front of the colon (:)
 * in the event name. If there is no colon present, they should live at
 * the top level.
 */
return [
    'auth' => [
        'fail' => 'Failed log in',
        'success' => 'Logged in',
        'password-reset' => 'Password reset',
        'reset-password' => 'Requested password reset',
        'checkpoint' => 'Two-factor authentication requested',
        'recovery-token' => 'Used two-factor recovery token',
        'token' => 'Solved two-factor challenge',
        'ip-blocked' => 'Blocked request from unlisted IP address for :identifier',
        'sftp' => [
            'fail' => 'Failed SFTP log in',
        ],
    ],
    'user' => [
        'user' => [
            'create' => 'Created a new user :email',
        ],
        'account' => [
            'email-changed' => 'Changed email from :old to :new',
            'password-changed' => 'Changed password',
            'username-changed' => 'Changed username from :old to :new',
            // Hydra: warnings, bans and the autodelete addon.
            'warned' => 'Account warned: :reason',
            'banned' => 'Account banned: :reason',
            'unbanned' => 'Ban lifted',
            'ban-expired' => 'Ban expired',
            'auto-deleted' => 'Account :username deleted automatically',
        ],
        // Hydra: resource pool.
        'pool' => [
            'server-created' => 'Created server :name from the resource pool',
            'server-updated' => 'Changed the resources of server :name',
            'server-deleted' => 'Deleted server :name from the resource pool',
        ],
        'api-key' => [
            'create' => 'Created new API key :identifier',
            'delete' => 'Deleted API key :identifier',
        ],
        'ssh-key' => [
            'create' => 'Added SSH key :fingerprint to account',
            'delete' => 'Removed SSH key :fingerprint from account',
        ],
        'two-factor' => [
            'create' => 'Enabled two-factor auth',
            'delete' => 'Disabled two-factor auth',
        ],
    ],
    'server' => [
        // Hydra: autodelete addon.
        'auto-deleted' => 'Server :name deleted automatically',
        'reinstall' => 'Reinstalled server',
        'console' => [
            'command' => 'Executed ":command" on the server',
        ],
        'power' => [
            'start' => 'Started the server',
            'stop' => 'Stopped the server',
            'restart' => 'Restarted the server',
            'kill' => 'Killed the server process',
        ],
        'backup' => [
            'download' => 'Downloaded the :name backup',
            'delete' => 'Deleted the :name backup',
            'restore' => 'Restored the :name backup (deleted files: :truncate)',
            'restore-complete' => 'Completed restoration of the :name backup',
            'restore-failed' => 'Failed to complete restoration of the :name backup',
            'start' => 'Started a new backup :name',
            'complete' => 'Marked the :name backup as complete',
            'fail' => 'Marked the :name backup as failed',
            'lock' => 'Locked the :name backup',
            'unlock' => 'Unlocked the :name backup',
            'import' => 'Added the :name backup from the own SFTP server',
            'sftp-update' => 'Set up the SFTP server :host for backups',
            'sftp-delete' => 'Removed the SFTP server :host for backups',
        ],
        'database' => [
            'create' => 'Created new database :name',
            'rotate-password' => 'Password rotated for database :name',
            'delete' => 'Deleted database :name',
            'query' => 'Ran an SQL statement in database :name',
            'import' => 'Imported :file into database :name',
            'export' => 'Exported database :name',
            'phpmyadmin' => 'Opened database :name in phpMyAdmin',
        ],
        'file' => [
            'compress_one' => 'Compressed :directory:files.0',
            'compress_other' => 'Compressed :count files in :directory',
            'read' => 'Viewed the contents of :file',
            'copy' => 'Created a copy of :file',
            'create-directory' => 'Created directory :directory:name',
            'decompress' => 'Decompressed :files in :directory',
            'delete_one' => 'Deleted :directory:files.0',
            'delete_other' => 'Deleted :count files in :directory',
            'download' => 'Downloaded :file',
            'pull' => 'Downloaded a remote file from :url to :directory',
            'rename_one' => 'Renamed :directory:files.0.from to :directory:files.0.to',
            'rename_other' => 'Renamed :count files in :directory',
            'write' => 'Wrote new content to :file',
            'upload' => 'Began a file upload',
            'uploaded' => 'Uploaded :directory:file',
            // Hydra: trash of the file manager.
            'trash_one' => 'Moved :directory:files.0 to the trash',
            'trash_other' => 'Moved :count files in :directory to the trash',
            'trash-restore_one' => 'Restored :files.0 from the trash',
            'trash-restore_other' => 'Restored :count files from the trash',
            'trash-delete_one' => 'Deleted :files.0 from the trash for good',
            'trash-delete_other' => 'Deleted :count files from the trash for good',
            'trash-empty' => 'Emptied the trash',
        ],
        'minecraft' => [
            'install' => 'Installed :software :version (build :build)',
        ],
        'sftp' => [
            'denied' => 'Blocked SFTP access due to permissions',
            'create_one' => 'Created :files.0',
            'create_other' => 'Created :count new files',
            'write_one' => 'Modified the contents of :files.0',
            'write_other' => 'Modified the contents of :count files',
            'delete_one' => 'Deleted :files.0',
            'delete_other' => 'Deleted :count files',
            'create-directory_one' => 'Created the :files.0 directory',
            'create-directory_other' => 'Created :count directories',
            'rename_one' => 'Renamed :files.0.from to :files.0.to',
            'rename_other' => 'Renamed or moved :count files',
        ],
        'allocation' => [
            'create' => 'Added :allocation to the server',
            'notes' => 'Updated the notes for :allocation from ":old" to ":new"',
            'primary' => 'Set :allocation as the primary server allocation',
            'delete' => 'Deleted the :allocation allocation',
        ],
        'role' => [
            'create' => 'Created role :name',
            'update' => 'Changed role :name',
            'delete' => 'Deleted role :name',
        ],
        'import' => [
            'start' => 'Started an import from :host (:source) to :target (mode: :mode)',
        ],
        'game' => [
            'switch' => 'Switched the game from :from to :to (files kept: :kept)',
            'load' => 'Loaded the game profile :name (files kept: :kept)',
            'delete' => 'Deleted the game profile :name',
        ],
        'subdomain' => [
            'create' => 'Created the subdomain :name',
            'delete' => 'Deleted the subdomain :name',
        ],
        'proxy' => [
            'create' => 'Created a reverse proxy for :domain to :allocation',
            'delete' => 'Deleted the reverse proxy for :domain',
        ],
        'schedule' => [
            'create' => 'Created the :name schedule',
            'update' => 'Updated the :name schedule',
            'execute' => 'Manually executed the :name schedule',
            'delete' => 'Deleted the :name schedule',
            'triggered' => 'The :name schedule was started by the server (:reason)',
        ],
        'task' => [
            'create' => 'Created a new ":action" task for the :name schedule',
            'update' => 'Updated the ":action" task for the :name schedule',
            'delete' => 'Deleted a task for the :name schedule',
        ],
        'settings' => [
            'rename' => 'Renamed the server from :old to :new',
            'description' => 'Changed the server description from :old to :new',
            'lockdown-enable' => 'Put the server into lockdown',
            'lockdown-disable' => 'Lifted the lockdown of the server',
        ],
        'console' => [
            'mclogs' => 'Uploaded :file to mclo.gs: :url',
        ],
        'startup' => [
            'command' => 'Changed the startup command from ":old" to ":new"',
            'done' => 'Changed the messages that mark the server as running from ":old" to ":new"',
            'edit' => 'Changed the :variable variable from ":old" to ":new"',
            'image' => 'Updated the Docker Image for the server from :old to :new',
        ],
        'subuser' => [
            'create' => 'Added :username as a subuser',
            'update' => 'Updated the subuser permissions for :username',
            'delete' => 'Removed :username as a subuser',
        ],
    ],
];
