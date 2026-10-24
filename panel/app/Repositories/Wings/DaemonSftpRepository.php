<?php

namespace Pterodactyl\Repositories\Wings;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Service\Backup\SftpBackupException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: SFTP servers for backups, reached through a node (addon
 * "sftp_backups"). The node connects to them, the panel never does.
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonSftpRepository setNode(\Pterodactyl\Models\Node $node)
 */
class DaemonSftpRepository extends DaemonRepository
{
    /**
     * Connects to the target, trusting any host key, and returns the
     * fingerprint of the key ("fingerprint"), whether it differs from the
     * stored one ("changed") and the backups found ("files").
     *
     * @throws DaemonConnectionException
     * @throws SftpBackupException
     */
    public function test(array $target): array
    {
        return $this->request('/api/hydra/sftp/test', ['sftp' => $target]);
    }

    /**
     * Backups in the folder of the target: name, size and modified.
     *
     * @throws DaemonConnectionException
     * @throws SftpBackupException
     */
    public function files(array $target): array
    {
        return $this->request('/api/hydra/sftp/list', ['sftp' => $target])['files'] ?? [];
    }

    /**
     * @throws DaemonConnectionException
     * @throws SftpBackupException
     */
    public function delete(array $target, string $file): void
    {
        $this->request('/api/hydra/sftp/delete', ['sftp' => $target, 'file' => $file]);
    }

    private function request(string $uri, array $json): array
    {
        try {
            $response = $this->getHttpClient()->post($uri, ['json' => $json, 'timeout' => 60]);
        } catch (RequestException $exception) {
            // Wings answers problems with the SFTP server with a message.
            $body = $exception->getResponse() ? json_decode($exception->getResponse()->getBody()->__toString(), true) : null;
            if ($exception->getResponse()?->getStatusCode() === 400 && is_array($body) && !empty($body['error'])) {
                throw new SftpBackupException((string) $body['error']);
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true) ?? [];
    }
}
