<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\SurfaceService;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Http\Requests\Admin\Design\BrandingFormRequest;

/**
 * Hydra: accent color, logo, favicon and the boxes (Design > Colors & Logo).
 */
class BrandingController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BrandingService $branding,
        private SurfaceService $surfaces,
        private BackgroundService $backgrounds,
    ) {
    }

    public function index(): View
    {
        return view('admin.design.general', [
            'defaultAccent' => BrandingService::DEFAULT_ACCENT,
            'hasCustomLogo' => !is_null($this->branding->path('logo')),
            'hasCustomFavicon' => !is_null($this->branding->path('favicon')),
            'surfaces' => $this->surfaces->values(),
            'surfaceDefaults' => SurfaceService::DEFAULTS,
            'surfacePresets' => SurfaceService::PRESETS,
            'surfaceLimits' => SurfaceService::NUMBERS,
            // The preview shows the boxes in front of the dashboard background.
            'previewBackground' => $this->backgrounds->forSlot('dashboard'),
        ]);
    }

    public function update(BrandingFormRequest $request): RedirectResponse
    {
        $this->branding->setAccent($request->input('accent'));
        if ($request->has('protect_images')) {
            $this->branding->setProtectImages($request->boolean('protect_images'));
        }
        if ($request->has('surface')) {
            $this->surfaces->save((array) $request->input('surface'));
        }

        foreach (['logo', 'favicon'] as $type) {
            if ($request->hasFile($type)) {
                $this->branding->store($type, $request->file($type));
            } elseif ($request->boolean("reset_$type")) {
                $this->branding->reset($type);
            }
        }

        $this->alert->success(trans('admin/design.general.updated'))->flash();

        return redirect()->route('admin.design');
    }
}
