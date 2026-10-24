<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: users copy the files of another server onto theirs over SFTP, from
 * the settings page of the server. The node of the server does the transfer.
 */
class ImporterAddon extends Addon
{
    public function key(): string
    {
        return 'importer';
    }

    public function icon(): string
    {
        return 'cloud-download';
    }

    /**
     * Whether the current files may be kept as a game profile, which needs
     * the game library to load them again.
     */
    public function offersProfiles(): bool
    {
        return app(GamesAddon::class)->enabled();
    }

    public function toArray(): array
    {
        return ['enabled' => $this->enabled(), 'profiles' => $this->enabled() && $this->offersProfiles()];
    }
}
