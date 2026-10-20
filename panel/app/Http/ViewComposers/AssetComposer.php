<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Models\PanelLink;
use Illuminate\Database\QueryException;
use Pterodactyl\Services\Hydra\Icons;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\ServerNavigation;
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
