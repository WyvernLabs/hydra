<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Http\Requests\Admin\Design\BrandingFormRequest;

/**
 * Hydra: accent color, logo and favicon (Design > Colors & Logo).
 */
class BrandingController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private BrandingService $branding)
    {
    }

    public function index(): View
    {
        return view('admin.design.general', [
            'defaultAccent' => BrandingService::DEFAULT_ACCENT,
            'hasCustomLogo' => !is_null($this->branding->path('logo')),
            'hasCustomFavicon' => !is_null($this->branding->path('favicon')),
        ]);
    }

    public function update(BrandingFormRequest $request): RedirectResponse
    {
        $this->branding->setAccent($request->input('accent'));

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
