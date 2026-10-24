<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Pterodactyl\Contracts\Models\Identifiable;
use Pterodactyl\Models\Traits\HasRealtimeIdentifier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $server_id
 * @property string $uuid
 * @property bool $is_successful
 * @property bool $is_locked
 * @property string $name
 * @property string[] $ignored_files
 * @property string $disk
 * @property string|null $checksum
 * @property int $bytes
 * @property string|null $upload_id
 * @property int|null $hydra_sftp_target_id
 * @property string|null $hydra_sftp_file
 * @property string|null $hydra_error
 * @property SftpBackupTarget|null $sftpTarget
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property Server $server
 * @property \Pterodactyl\Models\AuditLog[] $audits
 */
#[Attributes\Identifiable('bkup')]
class Backup extends Model implements Identifiable
{
    /** @use HasFactory<\Database\Factories\BackupFactory> */
    use HasFactory;
    use SoftDeletes;
    use HasRealtimeIdentifier;

    public const RESOURCE_NAME = 'backup';

    public const ADAPTER_WINGS = 'wings';
    public const ADAPTER_AWS_S3 = 's3';
    // Hydra: stored on an SFTP server (addon "sftp_backups"), see $sftpTarget.
    public const ADAPTER_SFTP = 'sftp';

    protected $table = 'backups';

    protected bool $immutableDates = true;

    protected $casts = [
        'id' => 'int',
        'is_successful' => 'bool',
        'is_locked' => 'bool',
        'ignored_files' => 'array',
        'bytes' => 'int',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'is_successful' => false,
        'is_locked' => false,
        'checksum' => null,
        'bytes' => 0,
        'upload_id' => null,
    ];

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public static array $validationRules = [
        'server_id' => 'bail|required|numeric|exists:servers,id',
        'uuid' => 'required|uuid',
        'is_successful' => 'boolean',
        'is_locked' => 'boolean',
        'name' => 'required|string',
        'ignored_files' => 'array',
        'disk' => 'required|string',
        'checksum' => 'nullable|string',
        'bytes' => 'numeric',
        'upload_id' => 'nullable|string',
        'hydra_sftp_target_id' => 'nullable|integer',
        'hydra_sftp_file' => 'nullable|string|max:255',
        'hydra_error' => 'nullable|string|max:191',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Hydra: the SFTP server of a backup with the sftp adapter.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\SftpBackupTarget, $this>
     */
    public function sftpTarget(): BelongsTo
    {
        return $this->belongsTo(SftpBackupTarget::class, 'hydra_sftp_target_id');
    }

    /**
     * Hydra: name of the archive on the SFTP server.
     */
    public function sftpFile(): string
    {
        return $this->hydra_sftp_file ?: $this->uuid . '.tar.gz';
    }

    /**
     * Hydra: where the backup is kept, "panel" (the node or S3), "node_sftp"
     * (the SFTP server of the node) or "own_sftp" (the user's own server).
     */
    public function location(): string
    {
        if ($this->disk !== self::ADAPTER_SFTP) {
            return 'panel';
        }

        return $this->sftpTarget && !is_null($this->sftpTarget->server_id) ? 'own_sftp' : 'node_sftp';
    }
}
