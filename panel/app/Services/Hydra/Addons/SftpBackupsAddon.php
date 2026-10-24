<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\SftpBackupTarget;

/**
 * Hydra: backups on SFTP servers. Admins can store the backups of a node on
 * an SFTP server instead of the node's disk (node tab "Backups"), and users
 * can keep backups on their own SFTP server, which do not count against the
 * backup space of the server.
 */
class SftpBackupsAddon extends Addon
{
    public function key(): string
    {
        return 'sftp_backups';
    }

    public function icon(): string
    {
        return 'hard-drive-upload';
    }

    public function booleans(): array
    {
        return ['users'];
    }

    public function summary(): array
    {
        $nodes = SftpBackupTarget::query()->whereNotNull('node_id')->where('enabled', true)->count();

        return [
            trans('admin/addons.sftp_backups.summary_nodes', ['count' => $nodes]),
            trans($this->usersAllowed() ? 'admin/addons.sftp_backups.summary_users_on' : 'admin/addons.sftp_backups.summary_users_off'),
        ];
    }

    /**
     * Users may set up their own SFTP server for backups.
     */
    public function usersAllowed(): bool
    {
        return $this->enabled() && (bool) $this->get('users', true);
    }

    /**
     * The SFTP server new backups of the node go to, null for the node's disk.
     */
    public function nodeTarget(Node $node): ?SftpBackupTarget
    {
        if (!$this->enabled()) {
            return null;
        }

        /** @var SftpBackupTarget|null $target */
        $target = SftpBackupTarget::query()->where('node_id', $node->id)->where('enabled', true)->first();

        return $target && $target->host_key ? $target : null;
    }
}
