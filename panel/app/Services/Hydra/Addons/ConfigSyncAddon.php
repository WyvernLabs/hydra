<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: config sync. In the editor of the file manager users put YAML
 * configs into a storage of the server; after a plugin update they open the
 * newly created config, press "Sync" and the values of the stored config are
 * written into it. Keys that no longer exist are listed, taken over keys are
 * highlighted. The admin sets how many configs a server keeps and how large
 * they may be.
 */
class ConfigSyncAddon extends Addon
{
    public function key(): string
    {
        return 'config_sync';
    }

    public function icon(): string
    {
        return 'git-merge';
    }

    public function rules(): array
    {
        return [
            'max_entries' => 'required|integer|min:1|max:200',
            'max_size' => 'required|integer|min:16|max:4096',
        ];
    }

    public function summary(): array
    {
        return [trans('admin/addons.config_sync.summary', ['count' => $this->maxEntries(), 'size' => $this->maxSize()])];
    }

    /**
     * Configs a server may keep in its storage.
     */
    public function maxEntries(): int
    {
        return max(1, min(200, (int) $this->get('max_entries', 25)));
    }

    /**
     * Largest config in KiB.
     */
    public function maxSize(): int
    {
        return max(16, min(4096, (int) $this->get('max_size', 512)));
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.configSync).
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'maxSize' => $this->maxSize(),
        ];
    }
}
