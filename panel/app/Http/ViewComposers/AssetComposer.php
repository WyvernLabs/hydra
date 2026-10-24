<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Models\PanelLink;
use Illuminate\Database\QueryException;
use Pterodactyl\Services\Hydra\Icons;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Services\Hydra\PhpMyAdminService;
use Pterodactyl\Services\Hydra\ConsoleSettings;
use Pterodactyl\Services\Hydra\Addons\CpuAddon;
use Pterodactyl\Services\Hydra\Addons\McLogsAddon;
use Pterodactyl\Services\Hydra\Addons\TicketsAddon;
use Pterodactyl\Services\Hydra\Addons\PropertiesAddon;
use Pterodactyl\Services\Hydra\Addons\SftpBackupsAddon;
use Pterodactyl\Services\Hydra\Addons\DashboardAddon;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Services\Hydra\Addons\TurnstileAddon;
use Pterodactyl\Services\Hydra\Addons\RegistrationAddon;
use Pterodactyl\Services\Helpers\AssetHashService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Users\ProfileMediaService;

class AssetComposer
{
    use AvailableLanguages;

    /**
     * AssetComposer constructor.
     */
    public function __construct(
        private AssetHashService $assetHashService,
        private BrandingService $branding,
        private BackgroundService $backgrounds,
        private TurnstileAddon $turnstile,
        private RegistrationAddon $registration,
        private ServerNavigation $navigation,
    ) {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $view->with('asset', $this->assetHashService);
        $view->with('branding', $this->branding);
        $links = $this->links();
        $navigation = $this->navigation->toFrontend();

        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Hydra',
            // The language resolved for this request (user > browser > panel default).
            'locale' => app()->getLocale(),
            // All installed languages with their native names, e.g. ['de' => 'Deutsch'].
            'languages' => $this->getAvailableLanguages(true),
            // Hydra: Cloudflare Turnstile and the registration (Design > Addons).
            'turnstile' => $this->turnstile->toArray(),
            'registration' => $this->registration->enabled(),
            // Hydra: users can open their databases in phpMyAdmin (Design > Addons).
            'phpMyAdmin' => app(PhpMyAdminService::class)->isOffered(),
            // Hydra: overview page of the servers (Design > Addons).
            'dashboard' => app(DashboardAddon::class)->toArray(),
            // Hydra: support tickets in the account (Design > Addons).
            'tickets' => app(TicketsAddon::class)->toArray(),
            // Hydra: .properties file as a form (Design > Addons).
            'properties' => app(PropertiesAddon::class)->toArray(),
            // Hydra: users may keep backups on their own SFTP server (Design > Addons).
            'sftpBackups' => app(SftpBackupsAddon::class)->usersAllowed(),
            // Hydra: .env editor and Minecraft version changer (Design > Addons).
            'env' => app(\Pterodactyl\Services\Hydra\Addons\EnvAddon::class)->toArray(),
            'versions' => app(\Pterodactyl\Services\Hydra\Addons\VersionsAddon::class)->enabled(),
            // Hydra: trash of the file manager (Design > Addons).
            'trash' => app(\Pterodactyl\Services\Hydra\Addons\TrashAddon::class)->toArray(),
            // Hydra: game library (Design > Addons).
            'games' => app(\Pterodactyl\Services\Hydra\Addons\GamesAddon::class)->enabled(),
            // Hydra: console texts (Design > Console) and console addons.
            'console' => app(ConsoleSettings::class)->toFrontend(app()->getLocale()) + [
                'cpuNormalized' => app(CpuAddon::class)->enabled(),
                'mclogs' => app(McLogsAddon::class)->enabled() ? app(McLogsAddon::class)->path() : null,
            ],
            'branding' => $this->branding->toArray(),
            // Hydra: largest profile picture or banner in megabytes.
            'profileUploadLimit' => ProfileMediaService::uploadLimit(),
            // Hydra: links and pages for the sidebar and the login page.
            'links' => $links,
            // Hydra: categories and buttons of the server pages (Design > Navigation).
            'serverNavigation' => $navigation,
            // Hydra: drawing data of every icon picked in the admin area (Lucide).
            'icons' => Icons::subset(array_merge(
                ['circle'],
                $this->navigation->icons(),
                array_column($links, 'icon'),
            )),
            // Hydra: backgrounds of the login page and the dashboard.
            'backgrounds' => [
                'login' => $this->backgrounds->forSlot('login'),
                'dashboard' => $this->backgrounds->forSlot('dashboard'),
            ],
        ]);
    }

    private function links(): array
    {
        try {
            return PanelLink::query()->visible()->get()
                ->map(fn (PanelLink $link) => $link->toSidebarArray() + ['public' => $link->public])
                ->all();
        } catch (QueryException) {
            // The table does not exist yet while the panel is being updated.
            return [];
        }
    }
}
