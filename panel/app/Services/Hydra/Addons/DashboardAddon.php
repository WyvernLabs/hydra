<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: an overview page for every server: banner of the egg, SFTP details,
 * server details, live resources, upcoming schedules, limits and recent activity.
 */
class DashboardAddon extends Addon
{
    public function key(): string
    {
        return 'dashboard';
    }

    public function icon(): string
    {
        return 'layout-dashboard';
    }

    public function booleans(): array
    {
        return ['landing'];
    }

    public function summary(): array
    {
        return [
            trans($this->landing() ? 'admin/addons.dashboard.summary_landing' : 'admin/addons.dashboard.summary_console'),
        ];
    }

    /**
     * Servers open on their dashboard instead of the console.
     */
    public function landing(): bool
    {
        return (bool) $this->get('landing', true);
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.dashboard).
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled(), 'landing' => $this->enabled() && $this->landing()];
    }
}
