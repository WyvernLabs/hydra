<?php

namespace Pterodactyl\Services\Hydra\Nodes;

use Illuminate\Support\Str;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\ApiKey;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Services\Api\KeyCreationService;

/**
 * Hydra: installs Hydra Wings on a node from the panel (Nodes > Installation).
 *
 * The panel ships the wings installer of its own version (copied there by the
 * panel installer) and hands it out with every option already set: domain of
 * the node, SSL, panel URL, configuration token and node ID. Admins either run
 * it with one short command or let the panel run it over SSH.
 */
class NodeInstallService
{
    /**
     * How long an install command can be used.
     */
    public const CODE_TTL = 3600;

    public function __construct(
        private Encrypter $encrypter,
        private KeyCreationService $keyCreationService,
    ) {
    }

    /**
     * Whether the panel has a wings installer to hand out.
     */
    public function available(): bool
    {
        return is_file($this->installerPath());
    }

    /**
     * A one-time code for the install command, valid for an hour.
     */
    public function createCode(Node $node, User $admin): string
    {
        $code = Str::random(40);
        Cache::put("hydra:node-install:$code", ['node' => $node->id, 'user' => $admin->id], self::CODE_TTL);

        return $code;
    }

    /**
     * The install script for a code; the code is used up by it.
     */
    public function scriptForCode(string $code): ?string
    {
        $data = Cache::pull("hydra:node-install:$code");
        $node = $data ? Node::query()->find($data['node']) : null;
        $admin = $data ? User::query()->find($data['user']) : null;

        return $node && $admin ? $this->script($node, $admin) : null;
    }

    /**
     * The wings installer with all options of the node set in front of the
     * ones it is started with.
     *
     * @throws DisplayException
     */
    public function script(Node $node, User $admin): string
    {
        if (!$this->available()) {
            throw new DisplayException(__('admin/nodes.install.not_available'));
        }

        $args = [
            '--yes',
            '--fqdn', $node->fqdn,
            '--email', $admin->email,
            '--panel-url', rtrim((string) config('app.url'), '/'),
            '--token', $this->deploymentToken($admin),
            '--node', (string) $node->id,
            $this->usesCertificate($node) ? '--ssl' : '--no-ssl',
        ];
        $source = $this->source();
        if ($source) {
            array_push($args, '--source', $source['source'], '--ref', $source['ref'] ?: 'main');
        }

        $script = (string) file_get_contents($this->installerPath());
        $preset = "# Hydra: options of node \"{$this->comment($node->name)}\", set by the panel.\n"
            . 'set -- ' . implode(' ', array_map('escapeshellarg', $args)) . ' "$@"' . "\n";

        // Right after the shebang, before the installer reads its arguments.
        [$shebang, $rest] = array_pad(explode("\n", $script, 2), 2, '');

        return $shebang . "\n" . $preset . $rest;
    }

    /**
     * The short command for the console of the node.
     */
    public function command(string $code): string
    {
        return 'bash <(curl -fsSL ' . route('node-install', $code) . ')';
    }

    /**
     * The node gets a Let's Encrypt certificate when the panel talks to it
     * over HTTPS and it has a domain, not an IP address.
     */
    public function usesCertificate(Node $node): bool
    {
        return $node->scheme === 'https' && !filter_var($node->fqdn, FILTER_VALIDATE_IP);
    }

    /**
     * API key with read and write access to the nodes, the same one the
     * "Generate Token" button of the configuration tab hands out.
     */
    private function deploymentToken(User $admin): string
    {
        $key = ApiKey::query()
            ->where('user_id', $admin->id)
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('r_nodes', 3)
            ->first();

        if (!$key) {
            $key = $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
                'user_id' => $admin->id,
                'memo' => 'Automatically generated node deployment key.',
                'allowed_ips' => [],
            ], ['r_nodes' => 3]);
        }

        return $key->identifier . $this->encrypter->decrypt($key->token);
    }

    /**
     * Git source of the panel, so the node builds the same version. Null for a
     * panel installed from an uploaded folder.
     *
     * @return array{source: string, ref: ?string}|null
     */
    private function source(): ?array
    {
        $path = storage_path('app/hydra/installer/source.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) && is_string($data['source'] ?? null) && $data['source'] !== '' ? $data : null;
    }

    private function installerPath(): string
    {
        return storage_path('app/hydra/installer/wings-install.sh');
    }

    private function comment(string $value): string
    {
        return preg_replace('/[^\w .-]/u', '', $value) ?? '';
    }
}
