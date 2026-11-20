<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\Concerns;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Services\Hydra\Minecraft\Plugins\PluginSource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: icons of plugins and mods, loaded by the panel so browsers do not
 * contact the sources. Only https addresses of the given hosts work.
 */
trait ProxiesProjectIcons
{
    /**
     * @param string[] $hosts
     */
    protected function proxyIcon(string $url, array $hosts): Response
    {
        // Largest icon that is passed through, in bytes.
        $limit = 1048576;
        $types = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), $hosts, true) || isset($parts['user']) || isset($parts['port'])) {
            throw new NotFoundHttpException();
        }

        $icon = Cache::remember('hydra:plugins:icon:' . sha1($url), now()->addDay(), function () use ($url, $limit, $types) {
            try {
                $response = Http::withHeaders(['User-Agent' => PluginSource::USER_AGENT])->withoutRedirecting()->timeout(8)->get($url);
            } catch (ConnectionException) {
                return ['type' => null];
            }
            $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if (!$response->successful() || !in_array($type, $types, true) || strlen($response->body()) > $limit) {
                return ['type' => null];
            }

            return ['type' => $type, 'data' => base64_encode($response->body())];
        });

        if (empty($icon['type'])) {
            throw new NotFoundHttpException();
        }

        return new Response(base64_decode($icon['data']), 200, [
            'Content-Type' => $icon['type'],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
