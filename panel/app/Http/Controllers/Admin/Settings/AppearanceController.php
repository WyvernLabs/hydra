<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Http\Requests\Admin\Settings\AppearanceSettingsFormRequest;

class AppearanceController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BrandingService $branding,
        private BackgroundService $backgrounds,
    ) {
    }

    /**
     * Render the appearance settings (accent color, logo, favicon).
     */
    public function index(): View
    {
        return view('admin.settings.appearance', [
            'defaultAccent' => BrandingService::DEFAULT_ACCENT,
            'hasCustomLogo' => !is_null($this->branding->path('logo')),
            'hasCustomFavicon' => !is_null($this->branding->path('favicon')),
            'backgrounds' => collect(BackgroundService::SLOTS)
                ->mapWithKeys(fn (string $slot) => [$slot => $this->backgrounds->forSlot($slot)])
                ->all(),
        ]);
    }

    public function update(AppearanceSettingsFormRequest $request): RedirectResponse
    {
        $this->branding->setAccent($request->input('accent'));

        foreach (['logo', 'favicon'] as $type) {
            if ($request->hasFile($type)) {
                $this->branding->store($type, $request->file($type));
            } elseif ($request->boolean("reset_$type")) {
                $this->branding->reset($type);
            }
        }

        // Hydra: backgrounds of the login page and the dashboard.
        foreach (BackgroundService::SLOTS as $slot) {
            if ($request->input('reset_background') === $slot) {
                $this->backgrounds->resetSlot($slot);
            } else {
                $this->backgrounds->saveSlot(
                    $slot,
                    $request->file("bg_{$slot}_image"),
                    (int) $request->input("bg_{$slot}_blur", 0),
                    (int) $request->input("bg_{$slot}_dim", 0),
                );
            }
        }

        $this->alert->success(trans('admin/settings.appearance.updated'))->flash();

        return redirect()->route('admin.settings.appearance');
    }
}
