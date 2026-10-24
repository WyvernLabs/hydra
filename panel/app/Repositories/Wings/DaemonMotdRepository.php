<?php

namespace Pterodactyl\Repositories\Wings;

use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: MOTD of the Minecraft servers of a node (addon "motd").
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonMotdRepository setNode(\Pterodactyl\Models\Node $node)
 */
class DaemonMotdRepository extends DaemonRepository
{
    /**
     * @throws DaemonConnectionException
     */
    public function put(array $config): void
    {
        try {
            $this->getHttpClient()->put('/api/system/motd', ['json' => $config]);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
