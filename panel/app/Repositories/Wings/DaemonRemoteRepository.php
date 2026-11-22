<?php

namespace Pterodactyl\Repositories\Wings;

use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: points a node at a new panel URL after the domain of the panel
 * changed (Admin > Settings > Domain).
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonRemoteRepository setNode(\Pterodactyl\Models\Node $node)
 */
class DaemonRemoteRepository extends DaemonRepository
{
    /**
     * @throws DaemonConnectionException
     */
    public function put(string $remote): void
    {
        try {
            $this->getHttpClient()->put('/api/system/remote', ['json' => ['remote' => $remote], 'timeout' => 15]);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
