<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: files deleted in the file manager are moved into a trash on the node
 * instead of being removed. Users restore them or delete them for good; the
 * node removes them by itself after "days". Files in the trash still count
 * against the disk of the server.
 */
class TrashAddon extends Addon
{
    public function key(): string
    {
        return 'trash';
    }

    public function icon(): string
    {
        return 'trash';
    }

    public function rules(): array
    {
        return [
            'days' => 'required|integer|min:1|max:365',
        ];
    }

    public function summary(): array
    {
        return [trans('admin/addons.trash.summary', ['days' => $this->days()])];
    }

    /**
     * Days a file stays in the trash.
     */
    public function days(): int
    {
        return max(1, min(365, (int) $this->get('days', 7)));
    }

    /**
     * Seconds deleted files are kept, null while the addon is off so they are
     * deleted for good.
     */
    public function keepSeconds(): ?int
    {
        return $this->enabled() ? $this->days() * 86400 : null;
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.trash).
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled(),
            'days' => $this->days(),
        ];
    }
}
