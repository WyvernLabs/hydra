<?php

namespace Pterodactyl\Services\Hydra\Schedules;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: a task of a schedule posts a message to a webhook, e.g. of Discord.
 * Only https addresses in public networks are reached, and the address is
 * pinned to the IP that was checked, so the panel's own network stays closed.
 */
class WebhookSender
{
    public static function validUrl(string $url): bool
    {
        if (strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = trim($parts['host'], '[]');

        // Addresses given directly have to be public, names are checked when sending.
        return !filter_var($host, FILTER_VALIDATE_IP) || self::isPublic($host);
    }

    public static function isPublic(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Replaces {server}, {id}, {time} and {date} in the message.
     */
    public static function render(string $message, Server $server): string
    {
        $now = CarbonImmutable::now();

        return strtr($message, [
            '{server}' => $server->name,
            '{id}' => $server->uuidShort,
            '{time}' => $now->format('H:i'),
            '{date}' => $now->format('Y-m-d'),
        ]);
    }

    /**
     * @throws DisplayException
     */
    public function send(string $url, string $message, Server $server): void
    {
        if (!self::validUrl($url)) {
            throw new DisplayException(__('server/schedules.payload.url'));
        }

        $parts = parse_url($url);
        $host = trim($parts['host'], '[]');
        $port = $parts['port'] ?? 443;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : $this->resolve($host);
        if (is_null($ip) || !self::isPublic($ip)) {
            throw new DisplayException(__('server/schedules.payload.url'));
        }

        $content = self::render($message, $server);
        $response = Http::timeout(10)
            ->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, str_contains($ip, ':') ? "[$ip]" : $ip)]]])
            ->asJson()
            ->post($url, [
                // "content" is what Discord shows, the rest helps other services.
                'content' => $content,
                'server' => ['name' => $server->name, 'id' => $server->uuidShort],
            ]);

        if (!$response->successful()) {
            throw new DisplayException(__('server/schedules.webhook_failed', ['status' => $response->status()]));
        }
    }

    /**
     * The first address of the host, null when it has none or any of them is
     * not public.
     */
    private function resolve(string $host): ?string
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = array_values(array_filter(array_map(fn (array $r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
        if (empty($ips)) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!self::isPublic($ip)) {
                return null;
            }
        }

        return $ips[0];
    }
}
