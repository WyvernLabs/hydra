<?php

namespace Pterodactyl\Repositories\Wings;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Exceptions\Service\Hydra\NodeFirewallException;

/**
 * Hydra: firewall (UFW) and database host of a node.
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonFirewallRepository setNode(\Pterodactyl\Models\Node $node)
 */
class DaemonFirewallRepository extends DaemonRepository
{
    /**
     * State of UFW, its rules and the address the panel connects from.
     *
     * @throws DaemonConnectionException
     * @throws NodeFirewallException
     */
    public function state(): array
    {
        return $this->request('GET', '/api/system/firewall', [], 25);
    }

    public function install(): void
    {
        $this->request('POST', '/api/system/firewall/install', [], 300);
    }

    public function enable(): void
    {
        $this->request('POST', '/api/system/firewall/enable', [], 60);
    }

    public function disable(): void
    {
        $this->request('POST', '/api/system/firewall/disable', [], 60);
    }

    public function addRule(array $rule): void
    {
        $this->request('POST', '/api/system/firewall/rules', $rule, 60);
    }

    public function deleteRule(string $rule): void
    {
        $this->request('DELETE', '/api/system/firewall/rules', ['rule' => $rule], 60);
    }

    /**
     * Opens the database port for exactly these addresses.
     *
     * @param string[] $sources
     */
    public function syncMysql(int $port, array $sources): void
    {
        $this->request('PUT', '/api/system/firewall/mysql', ['port' => $port, 'sources' => array_values($sources)], 120);
    }

    /**
     * State of the database server of the node and of the last setup.
     */
    public function database(): array
    {
        return $this->request('GET', '/api/system/database', [], 25);
    }

    /**
     * Starts the setup of the database server in the background.
     */
    public function setupDatabase(array $data): void
    {
        $this->request('POST', '/api/system/database/setup', $data, 20);
    }

    /**
     * @throws DaemonConnectionException
     * @throws NodeFirewallException
     */
    private function request(string $method, string $uri, array $json, int $timeout): array
    {
        $options = ['timeout' => $timeout];
        if (!empty($json)) {
            $options['json'] = $json;
        }

        try {
            $response = $this->getHttpClient()->request($method, $uri, $options);
        } catch (RequestException $exception) {
            // Wings answers failures of ufw, apt or the database with a code.
            $body = $exception->getResponse() ? json_decode($exception->getResponse()->getBody()->__toString(), true) : null;
            if (is_array($body) && !empty($body['code'])) {
                throw new NodeFirewallException($body['code'], (string) ($body['detail'] ?? ''));
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true) ?? [];
    }
}
