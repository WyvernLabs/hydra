<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Http\Requests\Admin\Design\BackgroundsFormRequest;

/**
 * Hydra: pictures of the panel (Designer > Images): logo, favicon and the
 * backgrounds of the dashboard and the admin area. The login background is
 * set under Designer > Login, those of the eggs under Designer > Eggs. The
 * update still takes every slot that is sent.
 */
class BackgroundController extends Controller
{
    /**
     * Backgrounds shown on this page.
     */
    public const PAGE_SLOTS = ['dashboard', 'admin'];

    public function __construct(
        private AlertsMessageBag $alert,
        private BackgroundService $backgrounds,
        private BrandingService $branding,
    ) {
    }

    public function index(): View
    {
        return view('admin.design.backgrounds', [
            'backgrounds' => collect(self::PAGE_SLOTS)
                ->mapWithKeys(fn (string $slot) => [$slot => $this->backgrounds->forSlot($slot)])
                ->all(),
            'hasCustomLogo' => !is_null($this->branding->path('logo')),
            'hasCustomFavicon' => !is_null($this->branding->path('favicon')),
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function update(BackgroundsFormRequest $request): RedirectResponse
    {
        foreach (BackgroundService::SLOTS as $slot) {
            // Only the slots of the form that was sent.
            $sent = $request->has("bg_{$slot}_blur") || $request->has("bg_{$slot}_dim") || $request->hasFile("bg_{$slot}_image");
            if (!$sent && $request->input('reset_background') !== $slot) {
                continue;
            }

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

        foreach (['logo', 'favicon'] as $type) {
            if ($request->hasFile($type)) {
                $this->branding->store($type, $request->file($type));
            } elseif ($request->boolean("reset_$type")) {
                $this->branding->reset($type);
            }
        }
        if ($request->has('protect_images')) {
            $this->branding->setProtectImages($request->boolean('protect_images'));
        }

        $this->alert->success(trans('admin/design.backgrounds.saved'))->flash();

        return redirect()->route('admin.design.backgrounds');
    }
}
