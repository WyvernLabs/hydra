<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: users upload the files of their server to another server over SFTP,
 * from the settings page of the server (the counterpart of the importer). The
 * node of the server does the transfer.
 */
class ExporterAddon extends Addon
{
    public function key(): string
    {
        return 'exporter';
    }

    public function icon(): string
    {
        return 'cloud-upload';
    }

    public function toArray(): array
    {
        return ['enabled' => $this->enabled()];
    }
}
