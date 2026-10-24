<?php

namespace Pterodactyl\Repositories\Wings;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: reverse proxies of a node (addon "proxy"), served by Caddy.
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonProxyRepository setNode(\Pterodactyl\Models\Node $node)
 */
class DaemonProxyRepository extends DaemonRepository
{
    /**
     * Replaces every proxy of the node.
     *
     * @param array<int, array{domain: string, upstream: string}> $proxies
     *
     * @throws DaemonConnectionException
     * @throws DisplayException
     */
    public function sync(array $proxies): void
    {
        try {
            $this->getHttpClient()->put('/api/system/proxies', ['json' => ['proxies' => $proxies], 'timeout' => 60]);
        } catch (RequestException $exception) {
            $code = $exception->getResponse() ? (json_decode($exception->getResponse()->getBody()->__toString(), true)['code'] ?? null) : null;
            if (is_string($code)) {
                throw new DisplayException(trans()->has("server/network.proxy_errors.$code") ? __("server/network.proxy_errors.$code") : __('server/network.proxy_errors.command_failed'));
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
