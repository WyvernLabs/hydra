<?php

namespace Pterodactyl\Services\Backups;

use Ramsey\Uuid\Uuid;
use Carbon\CarbonImmutable;
use Webmozart\Assert\Assert;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Extensions\Backups\BackupManager;
use Pterodactyl\Repositories\Eloquent\BackupRepository;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;
use Pterodactyl\Services\Hydra\Backups\BackupStorage;
use Pterodactyl\Services\Hydra\Addons\SftpBackupsAddon;
use Pterodactyl\Exceptions\Service\Backup\BackupStorageException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class InitiateBackupService
{
    private array $ignoredFiles = [];

    private bool $isLocked = false;

    private bool $ownSftp = false;

    /**
     * InitiateBackupService constructor.
     */
    public function __construct(
        private BackupRepository $repository,
        private ConnectionInterface $connection,
        private DaemonBackupRepository $daemonBackupRepository,
        private DeleteBackupService $deleteBackupService,
        private BackupManager $backupManager,
        private SftpBackupsAddon $sftpAddon,
    ) {
    }

    /**
     * Hydra: store the backup on the own SFTP server of the server's users
     * instead of the backup space of the server.
     */
    public function setOwnSftp(bool $ownSftp): self
    {
        $this->ownSftp = $ownSftp;

        return $this;
    }

    /**
     * Set if the backup should be locked once it is created which will prevent
     * its deletion by users or automated system processes.
     */
    public function setIsLocked(bool $isLocked): self
    {
        $this->isLocked = $isLocked;

        return $this;
    }

    /**
     * Sets the files to be ignored by this backup.
     *
     * @param string[]|null $ignored
     */
    public function setIgnoredFiles(?array $ignored): self
    {
        if (is_array($ignored)) {
            foreach ($ignored as $value) {
                Assert::string($value); // @phpstan-ignore staticMethod.alreadyNarrowedType
            }
        }

        // Set the ignored files to be any values that are not empty in the array. Don't use
        // the PHP empty function here incase anything that is "empty" by default (0, false, etc.)
        // were passed as a file or folder name.
        $this->ignoredFiles = is_null($ignored) ? [] : array_filter($ignored, function ($value) {
            return strlen($value) > 0;
        });

        return $this;
    }

    /**
     * Initiates the backup process for a server on Wings.
     *
     * @throws \Throwable
     * @throws BackupStorageException
     * @throws TooManyRequestsHttpException
     */
    public function handle(Server $server, ?string $name = null, bool $override = false): Backup
    {
        $limit = config('backups.throttles.limit');
        $period = config('backups.throttles.period');
        if ($period > 0) {
            $previous = $this->repository->getBackupsGeneratedDuringTimespan($server->id, $period);
            if ($previous->count() >= $limit) {
                $message = sprintf('Only %d backups may be generated within a %d second span of time.', $limit, $period);

                throw new TooManyRequestsHttpException((int) CarbonImmutable::now()->diffInSeconds($previous->last()->created_at->addSeconds($period)), $message);
            }
        }

        // Hydra: the backup goes to the user's own SFTP server, the SFTP server of
        // the node or the default adapter. Only the latter two use the backup space.
        $target = null;
        $maxSize = 0;
        if ($this->ownSftp) {
            $target = $server->sftpBackupTarget;
            if (!$this->sftpAddon->usersAllowed() || !$target || !$target->host_key) {
                throw new BackupStorageException('no_sftp');
            }
        } else {
            $target = $this->sftpAddon->nodeTarget($server->node);
            $maxSize = $this->reserveSpace($server, $override);
        }

        return $this->connection->transaction(function () use ($server, $name, $target, $maxSize) {
            $disk = $target ? Backup::ADAPTER_SFTP : $this->backupManager->getDefaultAdapter();

            /** @var Backup $backup */
            $backup = $this->repository->create([
                'server_id' => $server->id,
                'uuid' => Uuid::uuid4()->toString(),
                'name' => trim($name ?? '') ?: sprintf('Backup at %s', CarbonImmutable::now()->toDateTimeString()),
                'ignored_files' => array_values($this->ignoredFiles),
                'disk' => $disk,
                'hydra_sftp_target_id' => $target?->id,
                'is_locked' => $this->isLocked,
            ], true, true);

            $this->daemonBackupRepository->setServer($server)
                ->setBackupAdapter($disk)
                ->backup($backup, $maxSize);

            return $backup;
        });
    }

    /**
     * Hydra: makes sure there is backup space left and returns how many bytes
     * the new backup may use. Scheduled backups (override) delete the oldest
     * unlocked backups until a backup as large as the last one fits.
     *
     * @throws BackupStorageException
     * @throws \Throwable
     */
    private function reserveSpace(Server $server, bool $override): int
    {
        $limit = BackupStorage::limit($server);
        if ($limit <= 0) {
            throw new BackupStorageException('disabled');
        }

        $used = BackupStorage::used($server);
        if ($override) {
            $estimate = (int) BackupStorage::counted($server)->where('is_successful', true)->latest('created_at')->value('bytes');
            while ($used + $estimate > $limit) {
                /** @var Backup|null $oldest */
                $oldest = BackupStorage::counted($server)->where('is_successful', true)
                    ->where('is_locked', false)->orderBy('created_at')->first();
                if (!$oldest) {
                    break;
                }

                $this->deleteBackupService->handle($oldest);
                $used = BackupStorage::used($server);
            }
        }

        if ($used >= $limit) {
            throw new BackupStorageException('full');
        }

        return $limit - $used;
    }
}
