<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Models\Node;
use Illuminate\Support\Carbon;
use Pterodactyl\Repositories\Wings\DaemonRemoteRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: change the domain of the panel from the admin area (Settings > Domain).
 *
 * Like the updates, the panel never changes the server itself. It leaves a
 * "request" file (JSON) in the domain directory, which a systemd path unit set
 * up by the installer picks up to run "hydra-panel domain" as root. That
 * switches Caddy, the .env, phpMyAdmin and afterwards (through the artisan
 * command hydra:domain:changed) the nodes. Files in the directory:
 *
 *  - current.json  domain, HTTPS and redirects in use (written by the installer)
 *  - status.json   state of the last change (running, success, failed)
 *  - domain.log    output of the last change
 *  - nodes.json    result of the last time the nodes were pointed at the panel
 */
class DomainService
{
    public const SSL_MODES = ['auto', 'on', 'off'];

    /**
     * A change that has been "running" for longer than this has died.
     */
    private const STALE_AFTER = 15 * 60;

    private const LOG_MAX = 64 * 1024;

    public function __construct(private DaemonRemoteRepository $remote)
    {
    }

    public static function directory(): string
    {
        return storage_path('app/hydra/domain');
    }

    /**
     * Whether the installer has set up domain changes from the admin area.
     */
    public function isAvailable(): bool
    {
        return is_file(self::directory() . '/current.json');
    }

    /**
     * Domain, HTTPS and redirects in use. Without the installer the values come
     * from the panel URL.
     */
    public function current(): array
    {
        $data = $this->json('current.json') ?? [];
        $url = (string) config('app.url');

        return [
            'domain' => (string) ($data['domain'] ?? parse_url($url, PHP_URL_HOST) ?? ''),
            'ssl' => (bool) ($data['ssl'] ?? str_starts_with($url, 'https://')),
            'url' => (string) ($data['url'] ?? rtrim($url, '/')),
            'redirects' => array_values(array_filter((array) ($data['redirects'] ?? []), 'is_string')),
        ];
    }

    public function status(): ?array
    {
        return $this->json('status.json');
    }

    /**
     * Whether a request is waiting or a change is running.
     */
    public function isBusy(): bool
    {
        if (is_file(self::directory() . '/request')) {
            return true;
        }

        $status = $this->status();
        if (($status['state'] ?? null) !== 'running') {
            return false;
        }

        $started = isset($status['started_at']) ? strtotime((string) $status['started_at']) : false;

        return $started === false || time() - $started < self::STALE_AFTER;
    }

    /**
     * Output of the last change, without terminal colors.
     */
    public function log(): string
    {
        $path = self::directory() . '/domain.log';
        if (!is_file($path)) {
            return '';
        }

        $size = filesize($path);
        $log = (string) file_get_contents($path, false, null, max(0, $size - self::LOG_MAX));

        return InstallerLog::strip(preg_replace('/\e\[[0-9;]*m/', '', $log));
    }

    /**
     * Result of the last time the nodes were pointed at the panel.
     */
    public function nodes(): ?array
    {
        return $this->json('nodes.json');
    }

    /**
     * Turns input such as "https://Panel.Example.com/" into "panel.example.com".
     */
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $value);
        $value = explode('/', $value, 2)[0];

        return rtrim($value, '.');
    }

    /**
     * A host name with at least one dot, or an IPv4 address. The installer
     * checks the same before it changes anything.
     */
    public static function isValid(string $domain): bool
    {
        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $domain)) {
            return filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }

        return strlen($domain) <= 253
            && preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]([a-z0-9-]{0,61}[a-z0-9])?$/', $domain) === 1;
    }

    public static function isIp(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Addresses the domain resolves to (IPv4 and IPv6).
     *
     * @return string[]
     */
    public function resolve(string $domain): array
    {
        if (self::isIp($domain)) {
            return [$domain];
        }

        $ips = @gethostbynamel($domain) ?: [];
        foreach (@dns_get_record($domain, DNS_AAAA) ?: [] as $record) {
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Asks the helper to change the domain.
     *
     * @param string[] $drop redirects to remove
     */
    public function request(string $domain, string $ssl, bool $redirectOld, array $drop, bool $force): void
    {
        if (!self::isValid($domain) || !in_array($ssl, self::SSL_MODES, true)) {
            throw new \InvalidArgumentException('Invalid domain change request.');
        }

        $drop = array_values(array_filter($drop, fn ($value) => is_string($value) && self::isValid($value)));

        @unlink(self::directory() . '/nodes.json');
        file_put_contents(self::directory() . '/request', json_encode([
            'domain' => $domain,
            'ssl' => $ssl,
            'redirect_old' => $redirectOld,
            'drop' => $drop,
            'force' => $force,
        ]));
    }

    /**
     * The panel URL the domain will lead to.
     */
    public static function url(string $domain, string $ssl): string
    {
        $https = match ($ssl) {
            'on' => true,
            'off' => false,
            default => !self::isIp($domain),
        };

        return ($https ? 'https://' : 'http://') . $domain;
    }

    /**
     * Points every node at the panel URL and remembers the result.
     *
     * @return array<int, array{id: int, name: string, ok: bool, code: string|null, error: string|null}>
     */
    public function syncNodes(?string $url = null): array
    {
        $url = rtrim($url ?? (string) config('app.url'), '/');

        $results = Node::query()->orderBy('name')->get()
            ->map(fn (Node $node) => $this->syncNode($node, $url))
            ->values()
            ->all();

        $this->put('nodes.json', ['url' => $url, 'synced_at' => Carbon::now()->toIso8601String(), 'nodes' => $results]);

        return $results;
    }

    /**
     * @return array{id: int, name: string, ok: bool, code: string|null, error: string|null}
     */
    public function syncNode(Node $node, string $url): array
    {
        $result = ['id' => $node->id, 'name' => $node->name, 'ok' => true, 'code' => null, 'error' => null];

        try {
            $this->remote->setNode($node)->put($url);
        } catch (DaemonConnectionException $exception) {
            $result['ok'] = false;
            // 404: Wings is older than this feature, 409: the node ignores
            // configuration changes from the panel, 504: not reachable.
            $result['code'] = match ($exception->getStatusCode()) {
                404 => 'outdated',
                409 => 'ignored',
                504 => 'unreachable',
                default => 'error',
            };
            $result['error'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Data for the page and its live updates.
     */
    public function toArray(): array
    {
        return [
            'available' => $this->isAvailable(),
            'busy' => $this->isBusy(),
            'current' => $this->current(),
            'status' => $this->status(),
            'log' => $this->log(),
            'nodes' => $this->nodes(),
        ];
    }

    private function json(string $file): ?array
    {
        $path = self::directory() . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    private function put(string $file, array $data): void
    {
        $directory = self::directory();
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        file_put_contents($directory . '/' . $file, json_encode($data));
    }
}
