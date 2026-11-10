<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: "Download from link" in the file manager. The node of the server
 * fetches a file from a direct link (e.g. a backup or a plugin) into a folder
 * of the server, with progress. Addresses in private networks are refused by
 * the node.
 */
class LinkDownloadAddon extends Addon
{
    public function key(): string
    {
        return 'link_download';
    }

    public function icon(): string
    {
        return 'link';
    }

    public function toArray(): array
    {
        return ['enabled' => $this->enabled()];
    }
}
