<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: game profiles of a server on its node (addon "games").
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonProfileRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonProfileRepository extends DaemonRepository
{
    /**
     * Moves every file of the server into the profile, returns its size.
     */
    public function save(string $profile): int
    {
        return (int) ($this->request('save', $profile)['size'] ?? 0);
    }

    /**
     * Moves the files of the profile back into the empty server directory.
     */
    public function load(string $profile): void
    {
        $this->request('load', $profile);
    }

    /**
     * Removes every file of the server, the profiles stay.
     */
    public function wipe(): void
    {
        $this->request('wipe', '');
    }

    public function delete(string $profile): void
    {
        $this->request('delete', $profile);
    }

    /**
     * @throws DaemonConnectionException
     * @throws DisplayException
     */
    private function request(string $action, string $profile): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->post(
                sprintf('/api/servers/%s/hydra/profiles', $this->server->uuid),
                ['json' => ['action' => $action, 'profile' => $profile], 'timeout' => 300]
            );
        } catch (RequestException $exception) {
            $code = $exception->getResponse() ? (json_decode($exception->getResponse()->getBody()->__toString(), true)['code'] ?? null) : null;
            if (is_string($code) && trans()->has("server/games.errors.$code")) {
                throw new DisplayException(__("server/games.errors.$code"));
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true) ?? [];
    }
}
