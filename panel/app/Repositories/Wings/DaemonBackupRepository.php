<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonBackupRepository setNode(\Pterodactyl\Models\Node $node)
 * @method \Pterodactyl\Repositories\Wings\DaemonBackupRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonBackupRepository extends DaemonRepository
{
    protected ?string $adapter;

    /**
     * Sets the backup adapter for this execution instance.
     */
    public function setBackupAdapter(string $adapter): self
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Tells the remote Daemon to begin generating a backup for the server.
     *
     * @throws DaemonConnectionException
     */
    public function backup(Backup $backup, int $maxSize = 0): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        $json = [
            'adapter' => $this->adapter ?? config('backups.default'),
            'uuid' => $backup->uuid,
            'ignore' => implode("\n", $backup->ignored_files),
            // Hydra: space left for backups in bytes, 0 is no limit.
            'max_size' => $maxSize,
        ];
        // Hydra: backups on an SFTP server carry the server they go to.
        if ($backup->disk === Backup::ADAPTER_SFTP) {
            $json['adapter'] = Backup::ADAPTER_SFTP;
            $json['sftp'] = $backup->sftpTarget?->toDaemon();
            $json['file'] = $backup->sftpFile();
        }

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/backup', $this->server->uuid),
                ['json' => $json]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Sends a request to Wings to begin restoring a backup for a server.
     *
     * @throws DaemonConnectionException
     */
    public function restore(Backup $backup, ?string $url = null, bool $truncate = false): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/backup/%s/restore', $this->server->uuid, $backup->uuid),
                [
                    'json' => [
                        'adapter' => $backup->disk,
                        'truncate_directory' => $truncate,
                        'download_url' => $url ?? '',
                    ] + ($backup->disk === Backup::ADAPTER_SFTP ? [
                        'sftp' => $backup->sftpTarget?->toDaemon(),
                        'file' => $backup->sftpFile(),
                    ] : []),
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Deletes a backup from the daemon.
     *
     * @throws DaemonConnectionException
     */
    /**
     * Hydra: announces the download of a backup on an SFTP server and returns
     * the id the signed download link has to name.
     *
     * @throws DaemonConnectionException
     */
    public function sftpDownload(Backup $backup): string
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->post(
                sprintf('/api/servers/%s/backup/%s/sftp-download', $this->server->uuid, $backup->uuid),
                ['json' => ['sftp' => $backup->sftpTarget?->toDaemon(), 'file' => $backup->sftpFile()]]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return (string) (json_decode($response->getBody()->__toString(), true)['id'] ?? '');
    }

    public function delete(Backup $backup): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->delete(
                sprintf('/api/servers/%s/backup/%s', $this->server->uuid, $backup->uuid)
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
