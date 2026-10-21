<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Support\Str;
use Pterodactyl\Models\Node;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\DatabaseHost;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Repositories\Wings\DaemonFirewallRepository;
use Pterodactyl\Services\Databases\Hosts\HostUpdateService;
use Pterodactyl\Services\Databases\Hosts\HostCreationService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: sets up MariaDB on a node as database host in one click.
 *
 * Wings installs and configures the database server in the background and
 * opens the port in UFW for the panel and every node only. Once it is done
 * the database host is added to the panel (or updated, when the setup runs
 * again). The firewall rules of every such host follow the nodes of the panel.
 */
class NodeDatabaseService
{
    /** Addresses allowed in addition to the nodes, usually the panel. */
    public const SETTING_SOURCES = 'settings::hydra:database:sources';

    public const USERNAME = 'hydra';

    private const PENDING = 'hydra:database-setup:';

    public function __construct(
        private DaemonFirewallRepository $daemon,
        private SettingsRepositoryInterface $settings,
        private HostCreationService $creation,
        private HostUpdateService $updater,
        private Encrypter $encrypter,
        private Cache $cache,
    ) {
    }

    /**
     * The database host set up on this node, if any.
     */
    public function host(Node $node): ?DatabaseHost
    {
        return DatabaseHost::query()->where('node_id', $node->id)->where('hydra_managed', true)->first();
    }

    /**
     * @return string[]
     */
    public function extraSources(): array
    {
        $value = $this->settings->get(self::SETTING_SOURCES, '');

        return self::parseSources(is_string($value) ? $value : '');
    }

    /**
     * Public addresses of every node: from its domain and its allocations.
     *
     * @return array<int, string[]> keyed by node id
     */
    public function nodeAddresses(): array
    {
        $list = [];
        foreach (Node::query()->get() as $node) {
            $list[$node->id] = $this->addressesOf($node);
        }

        return $list;
    }

    /**
     * @return string[]
     */
    public function addressesOf(Node $node): array
    {
        $addresses = [];
        if (filter_var($node->fqdn, FILTER_VALIDATE_IP)) {
            $addresses[] = $node->fqdn;
        } else {
            $addresses = array_merge($addresses, @gethostbynamel($node->fqdn) ?: []);
            foreach (@dns_get_record($node->fqdn, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }
        $addresses = array_merge($addresses, $node->allocations()->distinct()->pluck('ip')->all());

        return array_values(array_unique(array_filter($addresses, fn ($ip) => self::isPublicAddress($ip))));
    }

    /**
     * Every address the database port is opened for.
     *
     * @return string[]
     */
    public function sources(?array $extra = null): array
    {
        $sources = $extra ?? $this->extraSources();
        foreach ($this->nodeAddresses() as $addresses) {
            $sources = array_merge($sources, $addresses);
        }

        return array_values(array_unique($sources));
    }

    /**
     * Address the servers and the panel connect to: the first IPv4 address of
     * the node.
     */
    public function defaultHost(Node $node): string
    {
        foreach ($this->addressesOf($node) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }

        return $node->fqdn;
    }

    /**
     * Starts the setup on the node.
     *
     * @param array{name: string, host: string, port: int, sources: string[], enable_firewall: bool} $data
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     * @throws \Pterodactyl\Exceptions\Service\Hydra\NodeFirewallException
     */
    public function start(Node $node, array $data): void
    {
        $this->settings->set(self::SETTING_SOURCES, implode("\n", $data['sources']));

        $id = (string) Str::uuid();
        $password = Str::random(40);

        $this->cache->put(self::PENDING . $node->id, $this->encrypter->encrypt([
            'id' => $id,
            'name' => $data['name'],
            'host' => $data['host'],
            'port' => (int) $data['port'],
            'password' => $password,
        ]), now()->addDay());

        try {
            $this->daemon->setNode($node)->setupDatabase([
                'id' => $id,
                'username' => self::USERNAME,
                'password' => $password,
                'port' => (int) $data['port'],
                'sources' => $this->sources($data['sources']),
                'enable_firewall' => (bool) $data['enable_firewall'],
            ]);
        } catch (\Throwable $exception) {
            $this->cache->forget(self::PENDING . $node->id);

            throw $exception;
        }
    }

    /**
     * State of the database server of the node. A finished setup is added to
     * the panel here, the first time its state is read.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     * @throws \Pterodactyl\Exceptions\Service\Hydra\NodeFirewallException
     */
    public function status(Node $node): array
    {
        $state = $this->daemon->setNode($node)->database();
        $setup = $state['setup'] ?? null;
        $state['finish_error'] = null;

        if (is_array($setup) && !empty($setup['success']) && $this->cache->has(self::PENDING . $node->id)) {
            $this->cache->lock(self::PENDING . 'lock:' . $node->id, 30)->get(function () use ($node, $setup, &$state) {
                $pending = $this->pending($node);
                if (!$pending || $pending['id'] !== ($setup['id'] ?? null)) {
                    return;
                }
                $this->cache->forget(self::PENDING . $node->id);

                try {
                    $this->finish($node, $pending);
                } catch (\Throwable $exception) {
                    Log::warning($exception);
                    $state['finish_error'] = $exception->getMessage();
                }
            });
        }

        $state['pending'] = $this->cache->has(self::PENDING . $node->id);

        return $state;
    }

    /**
     * Adds the database host to the panel or updates the one set up before.
     * Both test the connection from the panel.
     */
    private function finish(Node $node, array $pending): DatabaseHost
    {
        $data = [
            'name' => $pending['name'],
            'host' => $pending['host'],
            'port' => $pending['port'],
            'username' => self::USERNAME,
            'password' => $pending['password'],
            'node_id' => $node->id,
        ];

        if ($host = $this->host($node)) {
            return $this->updater->handle($host->id, $data);
        }

        $host = $this->creation->handle($data);
        $host->forceFill(['hydra_managed' => true])->save();

        return $host;
    }

    private function pending(Node $node): ?array
    {
        $value = $this->cache->get(self::PENDING . $node->id);
        if (!is_string($value)) {
            return null;
        }

        try {
            return $this->encrypter->decrypt($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Opens the database port of every database host set up on a node for
     * the current nodes and addresses.
     *
     * @return array<int, string> errors keyed by node id
     */
    public function syncFirewall(): array
    {
        $errors = [];
        $sources = null;

        $hosts = DatabaseHost::query()->where('hydra_managed', true)->whereNotNull('node_id')->with('node')->get();
        foreach ($hosts as $host) {
            if (!$host->node) {
                continue;
            }
            $sources ??= $this->sources();

            try {
                $this->daemon->setNode($host->node)->syncMysql($host->port, $sources);
            } catch (\Throwable $exception) {
                Log::warning($exception);
                $errors[$host->node->id] = $exception->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Single IPs and networks, one per line or separated by commas.
     *
     * @return string[]
     */
    public static function parseSources(string $value): array
    {
        $list = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($list));
    }

    public static function isValidSource(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        $parts = explode('/', $value);
        if (count($parts) !== 2 || !filter_var($parts[0], FILTER_VALIDATE_IP) || !ctype_digit($parts[1])) {
            return false;
        }

        return (int) $parts[1] <= (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128);
    }

    private static function isPublicAddress(mixed $ip): bool
    {
        if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return !in_array($ip, ['0.0.0.0', '::', '::1'], true) && !str_starts_with($ip, '127.');
    }
}
