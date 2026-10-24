<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hydra: an SFTP server backups are stored on (addon "sftp_backups"). It
 * belongs either to a node, set by an admin and used instead of the disk of
 * the node, or to a server, as the own SFTP server of its users.
 *
 * @property int $id
 * @property int|null $node_id
 * @property int|null $server_id
 * @property bool $enabled
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string|null $password
 * @property string|null $private_key
 * @property string $path
 * @property string|null $host_key
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Node|null $node
 * @property Server|null $server
 */
class SftpBackupTarget extends Model
{
    public const RESOURCE_NAME = 'sftp_backup_target';

    protected $table = 'sftp_backup_targets';

    protected bool $immutableDates = true;

    protected $fillable = ['node_id', 'server_id', 'enabled', 'host', 'port', 'username', 'password', 'private_key', 'path', 'host_key'];

    protected $hidden = ['password', 'private_key'];

    protected $casts = [
        'enabled' => 'bool',
        'port' => 'int',
        'password' => 'encrypted',
        'private_key' => 'encrypted',
    ];

    protected $attributes = [
        'enabled' => false,
        'port' => 22,
        'path' => '',
    ];

    public static array $validationRules = [
        'node_id' => 'nullable|integer',
        'server_id' => 'nullable|integer',
        'enabled' => 'boolean',
        'host' => 'required|string|max:191',
        'port' => 'required|integer|between:1,65535',
        'username' => 'required|string|max:191',
        'path' => 'present|nullable|string|max:191',
        'host_key' => 'nullable|string|max:191',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class, 'hydra_sftp_target_id');
    }

    /**
     * The target as Wings expects it. Only targets of an admin may be in a
     * private network.
     */
    public function toDaemon(?string $hostKey = null): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => (string) $this->password,
            'private_key' => (string) $this->private_key,
            'path' => (string) $this->path,
            'host_key' => $hostKey ?? (string) $this->host_key,
            'allow_internal' => !is_null($this->node_id),
        ];
    }
}
