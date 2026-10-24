<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Ramsey\Uuid\Uuid;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\SftpBackupTarget;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Backups\SftpTargetService;
use Pterodactyl\Services\Hydra\Addons\SftpBackupsAddon;
use Pterodactyl\Transformers\Api\Client\BackupTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Hydra: the own SFTP server of a server's users for backups (addon
 * "sftp_backups"), and importing backups that are already on it.
 */
class SftpBackupController extends ClientApiController
{
    public function __construct(private SftpTargetService $service, private SftpBackupsAddon $addon)
    {
        parent::__construct();
    }

    public function show(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BACKUP_READ);

        return SftpTargetService::toArray($server->sftpBackupTarget);
    }

    /**
     * Tests and saves the settings.
     *
     * @throws \Throwable
     */
    public function update(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BACKUP_SFTP);
        if (!$this->addon->usersAllowed()) {
            throw new BadRequestHttpException(__('server/backups.no_sftp'));
        }

        $target = $server->sftpBackupTarget ?? new SftpBackupTarget(['server_id' => $server->id, 'enabled' => true]);
        $data = $request->validate(SftpTargetService::rules() + ['clear_password' => 'sometimes|boolean']);
        if (empty($data['password']) && empty($data['private_key']) && !$target->password && !$target->private_key) {
            throw new BadRequestHttpException(__('server/backups.credentials_required'));
        }

        $result = $this->service->save($target, $data, $server->node);

        Activity::event('server:backup.sftp-update')->property('host', $target->host)->log();

        return SftpTargetService::toArray($target) + ['files' => $result['files'] ?? []];
    }

    /**
     * Removes the settings, unless backups are still stored on that server.
     */
    public function delete(Request $request, Server $server): JsonResponse
    {
        $this->check($request, $server, Permission::ACTION_BACKUP_SFTP);

        $target = $server->sftpBackupTarget;
        if ($target) {
            if ($target->backups()->where('is_successful', true)->exists()) {
                throw new BadRequestHttpException(__('server/backups.sftp_in_use'));
            }

            $target->delete();
            Activity::event('server:backup.sftp-delete')->property('host', $target->host)->log();
        }

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Backup archives on the SFTP server, marked when they are already known.
     *
     * @throws \Throwable
     */
    public function files(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BACKUP_CREATE);
        $target = $this->target($server);

        $known = $target->backups()->get()->map(fn (Backup $backup) => $backup->sftpFile())->all();

        return array_map(fn (array $file) => $file + ['imported' => in_array($file['name'], $known, true)], $this->service->files($target, $server->node));
    }

    /**
     * Adds a backup archive found on the SFTP server to the backups.
     *
     * @throws \Throwable
     */
    public function import(Request $request, Server $server): array
    {
        $this->check($request, $server, Permission::ACTION_BACKUP_CREATE);
        $target = $this->target($server);
        $data = $request->validate([
            'file' => 'required|string|max:255',
            'name' => 'nullable|string|max:191',
        ]);

        $file = collect($this->service->files($target, $server->node))->firstWhere('name', $data['file']);
        if (!$file) {
            throw new BadRequestHttpException(__('server/backups.sftp_file_missing'));
        }
        if ($target->backups()->get()->contains(fn (Backup $backup) => $backup->sftpFile() === $file['name'])) {
            throw new BadRequestHttpException(__('server/backups.sftp_file_imported'));
        }

        /** @var Backup $backup */
        $backup = $server->backups()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => trim($data['name'] ?? '') ?: $file['name'],
            'ignored_files' => [],
            'disk' => Backup::ADAPTER_SFTP,
            'hydra_sftp_target_id' => $target->id,
            'hydra_sftp_file' => $file['name'],
            'is_successful' => true,
            'bytes' => (int) $file['size'],
            'completed_at' => CarbonImmutable::now(),
        ]);

        Activity::event('server:backup.import')->subject($backup)->property('name', $backup->name)->log();

        return $this->fractal->item($backup)
            ->transformWith($this->getTransformer(BackupTransformer::class))
            ->toArray();
    }

    private function target(Server $server): SftpBackupTarget
    {
        $target = $server->sftpBackupTarget;
        if (!$this->addon->usersAllowed() || !$target || !$target->host_key) {
            throw new BadRequestHttpException(__('server/backups.no_sftp'));
        }

        return $target;
    }

    private function check(Request $request, Server $server, string $permission): void
    {
        if (!$request->user()->can($permission, $server)) {
            throw new AuthorizationException();
        }
    }
}
