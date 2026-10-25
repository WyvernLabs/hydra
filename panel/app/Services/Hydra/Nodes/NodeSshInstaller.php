<?php

namespace Pterodactyl\Services\Hydra\Nodes;

use phpseclib3\Net\SFTP;
use Illuminate\Support\Str;
use phpseclib3\Crypt\EC;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use phpseclib3\Crypt\PublicKeyLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: runs the wings installer on a node over SSH (Nodes > Installation).
 *
 * The password (or key) of the admin is only used to start: the panel uploads
 * the installer, starts it as a systemd unit, so it keeps running when the
 * connection drops, and puts a key of its own into authorized_keys. With that
 * key it reads the log while the installer runs and removes the key again
 * when it is done; a timer on the node removes it after three hours anyway.
 * Nothing the admin typed is stored.
 */
class NodeSshInstaller
{
    private const LOG = '/var/log/hydra-node-install.log';
    private const EXIT = '/var/log/hydra-node-install.exit';
    private const STATE_TTL = 3 * 3600;
    private const LOG_BYTES = 60000;

    public function __construct(private NodeInstallService $install)
    {
    }

    /**
     * @throws DisplayException
     */
    public function start(Node $node, User $admin, string $host, int $port, string $username, ?string $password, ?string $privateKey): void
    {
        if ($this->state($node)['state'] === 'running') {
            throw new DisplayException(__('admin/nodes.install.ssh.already_running'));
        }

        $id = Str::lower(Str::random(12));
        $sftp = $this->connect($host, $port);
        $credential = !empty($privateKey) ? $this->loadKey($privateKey, $password) : (string) $password;
        if (!$sftp->login($username, $credential)) {
            throw new DisplayException(__('admin/nodes.install.ssh.login_failed'));
        }

        $sudo = $this->sudoPrefix($sftp);
        $home = trim((string) $sftp->exec('printf %s "$HOME"'));
        if ($home === '' || !str_starts_with($home, '/')) {
            throw new DisplayException(__('admin/nodes.install.ssh.no_home'));
        }
        if (trim((string) $sftp->exec('command -v systemd-run')) === '') {
            throw new DisplayException(__('admin/nodes.install.ssh.no_systemd'));
        }

        $key = EC::createKey('Ed25519');
        $marker = "hydra-panel-install-$id";
        $public = $key->getPublicKey()->toString('OpenSSH', ['comment' => $marker]);
        $authorized = "$home/.ssh/authorized_keys";

        $script = "/tmp/hydra-node-install-$id.sh";
        if (!$sftp->put($script, $this->install->script($node, $admin))) {
            throw new DisplayException(__('admin/nodes.install.ssh.upload_failed'));
        }
        $sftp->chmod(0600, $script);

        $this->run($sftp, 'umask 077 && mkdir -p ' . escapeshellarg("$home/.ssh") . ' && printf "%s\n" ' . escapeshellarg($public) . ' >> ' . escapeshellarg($authorized));

        $job = 'bash ' . escapeshellarg($script) . ' > ' . self::LOG . ' 2>&1; echo $? > ' . self::EXIT . '; rm -f ' . escapeshellarg($script);
        $this->run($sftp, $sudo . 'rm -f ' . self::EXIT . ' ' . self::LOG);
        $this->run($sftp, $sudo . "systemd-run --quiet --collect --unit=hydra-node-install-$id /bin/bash -c " . escapeshellarg($job));
        // Removes the key of the panel even if nobody looks at the page again.
        $this->run($sftp, $sudo . "systemd-run --quiet --collect --on-active=3h --unit=hydra-node-install-cleanup-$id /bin/sed -i "
            . escapeshellarg("/$marker/d") . ' ' . escapeshellarg($authorized));

        Cache::put($this->cacheKey($node), [
            'id' => $id,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'sudo' => $sudo !== '',
            'authorized' => $authorized,
            'host_key' => $sftp->getServerPublicHostKey(),
            'key' => Crypt::encryptString($key->toString('OpenSSH')),
            'started_at' => now()->toAtomString(),
            'state' => 'running',
        ], self::STATE_TTL);

        $sftp->disconnect();
    }

    /**
     * State of the last installation over SSH with the end of its log:
     * none, running, success or failed.
     *
     * @return array{state: string, log?: string, exit_code?: ?int, host?: string, started_at?: string}
     */
    public function state(Node $node): array
    {
        $data = Cache::get($this->cacheKey($node));
        if (!is_array($data)) {
            return ['state' => 'none'];
        }
        if ($data['state'] !== 'running') {
            return $this->public($data);
        }

        try {
            $sftp = $this->connect($data['host'], $data['port']);
            if ($sftp->getServerPublicHostKey() !== $data['host_key']) {
                throw new DisplayException(__('admin/nodes.install.ssh.host_key_changed'));
            }
            if (!$sftp->login($data['username'], PublicKeyLoader::load(Crypt::decryptString($data['key'])))) {
                throw new DisplayException(__('admin/nodes.install.ssh.login_failed'));
            }
        } catch (\Throwable $exception) {
            return $this->public($data) + ['error' => $exception instanceof DisplayException
                ? $exception->getMessage()
                : __('admin/nodes.install.ssh.unreachable')];
        }

        $sudo = $data['sudo'] ? 'sudo -n ' : '';
        $output = (string) $sftp->exec(
            $sudo . 'tail -c ' . self::LOG_BYTES . ' ' . self::LOG . ' 2>/dev/null; echo; echo "__HYDRA__$(' . $sudo . 'cat ' . self::EXIT . ' 2>/dev/null)__$('
            . $sudo . 'systemctl is-active hydra-node-install-' . $data['id'] . ' 2>/dev/null)"'
        );
        preg_match('/__HYDRA__(\d*)__([a-z-]*)\s*$/', $output, $match);
        $data['log'] = $this->clean(preg_replace('/\n?__HYDRA__.*$/s', '', $output) ?? '');

        $exit = $match[1] ?? '';
        $active = $match[2] ?? '';
        if ($exit !== '' || !in_array($active, ['active', 'activating'], true)) {
            $data['exit_code'] = $exit === '' ? null : (int) $exit;
            $data['state'] = $exit === '0' ? 'success' : 'failed';
            // Done: the key of the panel is no longer needed.
            $sftp->exec('sed -i ' . escapeshellarg("/hydra-panel-install-{$data['id']}/d") . ' ' . escapeshellarg($data['authorized']));
            unset($data['key']);
        }
        $sftp->disconnect();

        Cache::put($this->cacheKey($node), $data, self::STATE_TTL);

        return $this->public($data);
    }

    /**
     * Forgets a finished installation, so the form shows up again.
     */
    public function forget(Node $node): void
    {
        $data = Cache::get($this->cacheKey($node));
        if (is_array($data) && $data['state'] !== 'running') {
            Cache::forget($this->cacheKey($node));
        }
    }

    private function connect(string $host, int $port): SFTP
    {
        try {
            $sftp = new SFTP($host, $port, 15);
            if (!$sftp->getServerPublicHostKey()) {
                throw new \RuntimeException('no host key');
            }

            return $sftp;
        } catch (\Throwable) {
            throw new DisplayException(__('admin/nodes.install.ssh.unreachable'));
        }
    }

    private function loadKey(string $privateKey, ?string $passphrase): mixed
    {
        try {
            return PublicKeyLoader::load($privateKey, empty($passphrase) ? false : $passphrase);
        } catch (\Throwable) {
            throw new DisplayException(__('admin/nodes.install.ssh.invalid_key'));
        }
    }

    /**
     * Empty for root, "sudo -n " for a user that may use sudo without a password.
     */
    private function sudoPrefix(SFTP $sftp): string
    {
        if (trim((string) $sftp->exec('id -u')) === '0') {
            return '';
        }
        if (trim((string) $sftp->exec('sudo -n true >/dev/null 2>&1 && echo ok')) === 'ok') {
            return 'sudo -n ';
        }

        throw new DisplayException(__('admin/nodes.install.ssh.not_root'));
    }

    private function run(SFTP $sftp, string $command): void
    {
        $output = (string) $sftp->exec($command . ' 2>&1');
        if ($sftp->getExitStatus() !== 0) {
            throw new DisplayException(__('admin/nodes.install.ssh.command_failed', ['output' => Str::limit(trim($output), 300)]));
        }
    }

    /**
     * Without colours and carriage returns of progress output.
     */
    private function clean(string $log): string
    {
        $log = preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $log) ?? $log;
        $log = preg_replace('/^.*\r(?!\n)/m', '', $log) ?? $log;

        return trim(str_replace("\r", '', $log));
    }

    private function public(array $data): array
    {
        return array_intersect_key($data, array_flip(['state', 'log', 'exit_code', 'host', 'username', 'started_at', 'error']));
    }

    private function cacheKey(Node $node): string
    {
        return "hydra:node-ssh-install:{$node->id}";
    }
}
