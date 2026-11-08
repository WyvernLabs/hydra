<?php

namespace Pterodactyl\Repositories\Wings;

use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Services\Hydra\InstallerLog;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: updates of a node, started from the admin area.
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonUpdateRepository setNode(\Pterodactyl\Models\Node $node)
 */
class DaemonUpdateRepository extends DaemonRepository
{
    /**
     * Version of Wings and the state and log of the last update.
     *
     * @throws DaemonConnectionException
     */
    public function status(): array
    {
        try {
            $response = $this->getHttpClient()->get('/api/system/update', ['timeout' => 10]);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        $data = json_decode($response->getBody()->__toString(), true) ?? [];
        if (is_string($data['log'] ?? null)) {
            // Progress lines of the installer become a bar (InstallerLog).
            $parsed = InstallerLog::parse($data['log']);
            $data['log'] = $parsed['log'];
            $data['progress'] = $parsed['progress'];
        }

        return $data;
    }

    /**
     * Starts the update of the node in the background.
     *
     * @throws DaemonConnectionException
     */
    public function start(): void
    {
        try {
            $this->getHttpClient()->post('/api/system/update', ['timeout' => 20]);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
