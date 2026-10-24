<?php

namespace Pterodactyl\Services\Hydra\Backups;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\SftpBackupTarget;
use Pterodactyl\Repositories\Wings\DaemonSftpRepository;

/**
 * Hydra: saves SFTP servers for backups (addon "sftp_backups"). Every change
 * is tested through the node first; the host key seen then is stored and has
 * to match on every later connection.
 */
class SftpTargetService
{
    public function __construct(private DaemonSftpRepository $daemon)
    {
    }

    /**
     * Validation rules of the form. Password and key are only replaced when
     * a new one is entered.
     */
    public static function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'port' => 'required|integer|between:1,65535',
            'username' => 'required|string|max:191',
            'password' => 'nullable|string|max:191',
            'private_key' => 'nullable|string|max:16000',
            'path' => ['nullable', 'string', 'max:191', 'regex:/^[^\x00\\\\]*$/'],
        ];
    }

    /**
     * Tests the settings through the node and saves them with the host key
     * that was seen. Returns the result of the test (fingerprint, files).
     *
     * @throws \Pterodactyl\Exceptions\Service\Backup\SftpBackupException
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function save(SftpBackupTarget $target, array $data, Node $via): array
    {
        $target->fill([
            'host' => trim($data['host'], '[]'),
            'port' => (int) $data['port'],
            'username' => $data['username'],
            'path' => trim((string) ($data['path'] ?? '')),
        ]);
        if (!empty($data['password'])) {
            $target->password = $data['password'];
        }
        if (!empty($data['private_key'])) {
            $target->private_key = trim($data['private_key']) . "\n";
        }
        if (!empty($data['clear_password'])) {
            $target->password = null;
        }

        $result = $this->daemon->setNode($via)->test($target->toDaemon(''));
        $target->host_key = $result['fingerprint'] ?? null;
        $target->save();

        return $result;
    }

    /**
     * Backups found on the target.
     *
     * @throws \Pterodactyl\Exceptions\Service\Backup\SftpBackupException
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function files(SftpBackupTarget $target, Node $via): array
    {
        return $this->daemon->setNode($via)->files($target->toDaemon());
    }

    /**
     * The settings for a form, without the secrets.
     */
    public static function toArray(?SftpBackupTarget $target): array
    {
        return [
            'configured' => !is_null($target) && !empty($target->host_key),
            'enabled' => (bool) $target?->enabled,
            'host' => $target?->host ?? '',
            'port' => $target?->port ?? 22,
            'username' => $target?->username ?? '',
            'path' => $target?->path ?? '',
            'host_key' => $target?->host_key,
            'has_password' => !empty($target?->password),
            'has_private_key' => !empty($target?->private_key),
        ];
    }
}
