<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * Hydra: stops a server and waits until it is offline, e.g. before its files
 * are replaced. A server that does not stop in time is killed.
 */
class ServerStopper
{
    // How long a running server gets to stop, in seconds.
    public const STOP_TIMEOUT = 30;

    public function __construct(
        private DaemonPowerRepository $power,
        private DaemonServerRepository $daemon,
    ) {
    }

    /**
     * @throws DisplayException when the server is still running afterwards
     */
    public function stop(Server $server, string $failedMessage): void
    {
        $state = fn () => $this->daemon->setServer($server)->getDetails()['state'] ?? 'offline';
        if ($state() === 'offline') {
            return;
        }

        $this->power->setServer($server)->send('stop');
        $deadline = time() + self::STOP_TIMEOUT;
        while (time() < $deadline) {
            sleep(1);
            if ($state() === 'offline') {
                return;
            }
        }

        $this->power->setServer($server)->send('kill');
        sleep(2);
        if ($state() !== 'offline') {
            throw new DisplayException($failedMessage);
        }
    }
}
