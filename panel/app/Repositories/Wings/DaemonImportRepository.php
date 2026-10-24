<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: import of another server over SFTP, done by the node of the server
 * (addon "importer").
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonImportRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonImportRepository extends DaemonRepository
{
    /**
     * Logs in and returns the fingerprint of the host key and what is in the source folder.
     */
    public function test(array $sftp, string $source): array
    {
        return $this->request('POST', 'import/test', ['sftp' => $sftp, 'source' => $source], 30);
    }

    public function start(array $sftp, string $source, string $target): void
    {
        $this->request('POST', 'import', ['sftp' => $sftp, 'source' => $source, 'target' => $target], 30);
    }

    public function status(): array
    {
        return $this->request('GET', 'import', null, 10);
    }

    public function cancel(): void
    {
        $this->request('DELETE', 'import', null, 10);
    }

    /**
     * Errors of the other server (wrong password, unknown folder, …) are
     * shown to the user as they are.
     *
     * @throws DaemonConnectionException
     * @throws DisplayException
     */
    private function request(string $method, string $path, ?array $json, int $timeout): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->request($method, sprintf('/api/servers/%s/hydra/%s', $this->server->uuid, $path), array_filter([
                'json' => $json,
                'timeout' => $timeout,
            ]));
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            if ($response && in_array($response->getStatusCode(), [400, 409], true)) {
                $error = json_decode($response->getBody()->__toString(), true)['error'] ?? null;
                if (is_string($error) && $error !== '') {
                    throw new DisplayException($error);
                }
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true) ?? [];
    }
}
