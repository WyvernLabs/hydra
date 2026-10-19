<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Models\PanelLink;
use Illuminate\Database\QueryException;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Helpers\AssetHashService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Users\ProfileMediaService;

class AssetComposer
{
    use AvailableLanguages;

    /**
     * AssetComposer constructor.
     */
    public function __construct(private AssetHashService $assetHashService, private BrandingService $branding)
    {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $view->with('asset', $this->assetHashService);
        $view->with('branding', $this->branding);
        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Hydra',
            // The language resolved for this request (user > browser > panel default).
            'locale' => app()->getLocale(),
            // All installed languages with their native names, e.g. ['de' => 'Deutsch'].
            'languages' => $this->getAvailableLanguages(true),
            'recaptcha' => [
                'enabled' => config('recaptcha.enabled', false),
                'siteKey' => config('recaptcha.website_key') ?? '',
            ],
            'branding' => $this->branding->toArray(),
            // Hydra: largest profile picture or banner in megabytes.
            'profileUploadLimit' => ProfileMediaService::uploadLimit(),
            // Hydra: links and pages for the sidebar and the login page.
            'links' => $this->links(),
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
