<?php

namespace Pterodactyl\Services\Hydra;

use Illuminate\Support\Str;
use Pterodactyl\Models\Database;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Services\Hydra\Addons\PhpMyAdminAddon;

/**
 * Hydra: phpMyAdmin on the panel server, installed and removed from the admin
 * area (Design > Addons > phpMyAdmin).
 *
 * Like the updates, the panel only leaves a "request" file ("install" or
 * "uninstall") in its directory. A systemd path unit set up by the installer
 * runs the phpMyAdmin helper as root, which writes back:
 *
 *  - status.json      installed, version, state (running, success, failed)
 *  - phpmyadmin.log   output of the last run
 *
 * Users are logged in with a one-time token: the panel stores the credentials
 * of the database in tokens/<sha256 of the token>.json, the signon script of
 * phpMyAdmin reads and deletes it within a minute.
 */
class PhpMyAdminService
{
    public const ACTIONS = ['install', 'uninstall'];

    public const PATH = '/phpmyadmin';

    private const TOKEN_LIFETIME = 60;

    private const LOG_MAX = 64 * 1024;

    public function __construct(private Encrypter $encrypter)
    {
    }

    public static function directory(): string
    {
        return storage_path('app/hydra/phpmyadmin');
    }

    /**
     * Whether the installer has set up the helper (panel installed or updated
     * with a version that knows phpMyAdmin).
     */
    public function isAvailable(): bool
    {
        return is_file(self::directory() . '/helper');
    }

    public function status(): array
    {
        $path = self::directory() . '/status.json';
        $status = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($status) ? $status : [];
    }

    public function isInstalled(): bool
    {
        return (bool) ($this->status()['installed'] ?? false);
    }

    public function isBusy(): bool
    {
        return is_file(self::directory() . '/request') || ($this->status()['state'] ?? null) === 'running';
    }

    /**
     * Whether users are offered phpMyAdmin.
     */
    public function isOffered(): bool
    {
        return $this->isInstalled() && app(PhpMyAdminAddon::class)->enabled();
    }

    public function request(string $action): void
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Unknown phpMyAdmin action $action.");
        }

        file_put_contents(self::directory() . '/request', $action);
    }

    public function log(): string
    {
        $path = self::directory() . '/phpmyadmin.log';
        if (!is_file($path)) {
            return '';
        }

        $size = filesize($path) ?: 0;
        $handle = fopen($path, 'rb');
        if ($size > self::LOG_MAX) {
            fseek($handle, -self::LOG_MAX, SEEK_END);
        }
        $log = (string) stream_get_contents($handle);
        fclose($handle);

        return preg_replace('/\e\[[0-9;]*m/', '', $log) ?? $log;
    }

    public function toArray(): array
    {
        $status = $this->status();

        return [
            'available' => $this->isAvailable(),
            'installed' => (bool) ($status['installed'] ?? false),
            'version' => $status['version'] ?? null,
            'state' => $status['state'] ?? null,
            'action' => $status['action'] ?? null,
            'finished_at' => $status['finished_at'] ?? null,
            'busy' => $this->isBusy(),
            'log' => $this->log(),
        ];
    }

    /**
     * A one-time link that logs the user in to phpMyAdmin with the
     * credentials of the database.
     */
    public function loginUrl(Database $database): string
    {
        $directory = self::directory() . '/tokens';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $this->purge($directory);

        $token = Str::random(64);
        $file = $directory . '/' . hash('sha256', $token) . '.json';

        file_put_contents($file, json_encode([
            'user' => $database->username,
            'password' => $this->encrypter->decrypt($database->password),
            'host' => $database->host->host,
            'port' => (int) $database->host->port,
            'database' => $database->database,
            'expires' => time() + self::TOKEN_LIFETIME,
        ]));
        chmod($file, 0600);

        return url(self::PATH . '/hydra-signon.php') . '?token=' . $token;
    }

    /**
     * Removes tokens that were never used.
     */
    private function purge(string $directory): void
    {
        foreach (glob($directory . '/*.json') ?: [] as $file) {
            if (filemtime($file) < time() - self::TOKEN_LIFETIME * 2) {
                @unlink($file);
            }
        }
    }
}
