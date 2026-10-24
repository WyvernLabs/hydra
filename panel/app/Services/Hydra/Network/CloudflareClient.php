<?php

namespace Pterodactyl\Services\Hydra\Network;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: the DNS records of a domain on Cloudflare (API v4). The token needs
 * the permission "Zone > DNS > Edit" for the zone.
 */
class CloudflareClient
{
    public const API = 'https://api.cloudflare.com/client/v4';

    public function __construct(private string $token)
    {
    }

    /**
     * Id of the zone with the given name.
     *
     * @throws DisplayException
     */
    public function zoneId(string $name): string
    {
        $zones = $this->request('get', '/zones', ['name' => $name]);
        if (empty($zones[0]['id'])) {
            throw new DisplayException(__('admin/domains.zone_not_found', ['name' => $name]));
        }

        return $zones[0]['id'];
    }

    /**
     * Records of the zone with exactly this name.
     *
     * @throws DisplayException
     */
    public function records(string $zone, string $name): array
    {
        return $this->request('get', "/zones/$zone/dns_records", ['name' => $name]);
    }

    /**
     * Creates a record and returns its id.
     *
     * @throws DisplayException
     */
    public function create(string $zone, array $record): string
    {
        return (string) $this->request('post', "/zones/$zone/dns_records", $record + ['ttl' => 1])['id'];
    }

    /**
     * Deletes a record; one that is gone already is no error.
     *
     * @throws DisplayException
     */
    public function delete(string $zone, string $id): void
    {
        $this->request('delete', "/zones/$zone/dns_records/$id", [], true);
    }

    /**
     * @throws DisplayException
     */
    private function request(string $method, string $path, array $data = [], bool $allowMissing = false): mixed
    {
        try {
            $response = $this->http()->{$method}(self::API . $path, $data);
        } catch (\Throwable $exception) {
            throw new DisplayException(__('admin/domains.unreachable'));
        }

        if ($allowMissing && $response->status() === 404) {
            return null;
        }
        if (!$response->successful() || !$response->json('success')) {
            $message = $response->json('errors.0.message') ?: ('HTTP ' . $response->status());

            throw new DisplayException(__('admin/domains.api_error', ['message' => $message]));
        }

        return $response->json('result');
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)->acceptJson()->timeout(15);
    }
}
