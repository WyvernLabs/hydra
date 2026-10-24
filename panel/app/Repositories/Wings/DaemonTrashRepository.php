<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: trash of the file manager of a server on its node. Deleted files are
 * kept there until they expire, see Services/Hydra/Addons/TrashAddon.
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonTrashRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonTrashRepository extends DaemonRepository
{
    /**
     * Every entry of the trash, the most recently deleted first.
     *
     * @return array<int, array{id: string, path: string, directory: bool, size: int, deleted_at: string, expires_at: string}>
     *
     * @throws DaemonConnectionException
     */
    public function list(): array
    {
        return $this->request('get', '')['entries'] ?? [];
    }

    /**
     * Moves the entries back to where they were deleted from and returns the
     * paths they were restored to, by id.
     *
     * @param string[] $ids
     *
     * @return array<string, string>
     */
    public function restore(array $ids): array
    {
        return $this->request('post', '/restore', ['ids' => array_values($ids)])['restored'] ?? [];
    }

    /**
     * Removes the entries for good.
     *
     * @param string[] $ids
     */
    public function delete(array $ids): void
    {
        $this->request('post', '/delete', ['ids' => array_values($ids)]);
    }

    /**
     * Removes every entry of the trash.
     */
    public function emptyTrash(): void
    {
        $this->request('post', '/delete', ['all' => true]);
    }

    /**
     * @throws DaemonConnectionException
     * @throws DisplayException
     */
    private function request(string $method, string $path, ?array $json = null): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->request(
                $method,
                sprintf('/api/servers/%s/hydra/trash%s', $this->server->uuid, $path),
                array_filter(['json' => $json, 'timeout' => 300])
            );
        } catch (RequestException $exception) {
            $code = $exception->getResponse() ? (json_decode($exception->getResponse()->getBody()->__toString(), true)['code'] ?? null) : null;
            if ($code === 'trash_not_found') {
                throw new DisplayException(__('client/files.trash.not_found'));
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true) ?? [];
    }
}
