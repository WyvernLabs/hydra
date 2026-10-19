<?php

namespace Pterodactyl\Services\Hydra;

/**
 * Hydra: updates of the panel from the admin area (Settings > Updates).
 *
 * The panel never updates itself. It leaves a "request" file in the update
 * directory, which a systemd path unit set up by the installer picks up to run
 * "hydra-panel update" (or only look for a new version) as root. The updater
 * writes these files back:
 *
 *  - installed.json  the commit that is installed (written by the installer)
 *  - latest.json     the newest commit of the configured source
 *  - status.json     state of the last update (running, success, failed)
 *  - panel.log       output of the last update
 */
class UpdateService
{
    public const ACTIONS = ['check', 'update'];

    private const LOG_MAX = 64 * 1024;

    public static function directory(): string
    {
        return storage_path('app/hydra/updates');
    }

    /**
     * Whether the installer has set up updates from the admin area.
     */
    public function isAvailable(): bool
    {
        return is_file(self::directory() . '/installed.json');
    }

    public function installed(): ?array
    {
        return $this->json('installed.json');
    }

    public function latest(): ?array
    {
        return $this->json('latest.json');
    }

    public function status(): ?array
    {
        return $this->json('status.json');
    }

    /**
     * Whether a request is waiting or an update is running.
     */
    public function isBusy(): bool
    {
        return is_file(self::directory() . '/request') || ($this->status()['state'] ?? null) === 'running';
    }

    /**
     * Whether the newest commit differs from the installed one.
     */
    public function hasUpdate(): ?bool
    {
        $installed = $this->installed()['commit'] ?? null;
        $latest = $this->latest()['commit'] ?? null;

        return $installed && $latest ? !hash_equals($installed, $latest) : null;
    }

    /**
     * The end of the log of the last update, without terminal colors.
     */
    public function log(): string
    {
        $path = self::directory() . '/panel.log';
        if (!is_file($path)) {
            return '';
        }

        $size = filesize($path);
        $log = (string) file_get_contents($path, false, null, max(0, $size - self::LOG_MAX));

        return preg_replace('/\e\[[0-9;]*m/', '', $log);
    }

    /**
     * Asks the updater to look for a new version or to update the panel.
     */
    public function request(string $action): void
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Unknown update action \"$action\".");
        }

        file_put_contents(self::directory() . '/request', $action);
    }

    /**
     * Data for the page and its live updates.
     */
    public function toArray(): array
    {
        return [
            'available' => $this->isAvailable(),
            'busy' => $this->isBusy(),
            'installed' => $this->installed(),
            'latest' => $this->latest(),
            'status' => $this->status(),
            'has_update' => $this->hasUpdate(),
            'log' => $this->log(),
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
}
