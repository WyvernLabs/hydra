<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Http\Requests\Admin\Design\BackgroundsFormRequest;

/**
 * Hydra: backgrounds of the login page and the dashboard (Design >
 * Backgrounds). Those of the eggs are set under Design > Eggs.
 */
class BackgroundController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private BackgroundService $backgrounds)
    {
    }

    public function index(): View
    {
        return view('admin.design.backgrounds', [
            'backgrounds' => collect(BackgroundService::SLOTS)
                ->mapWithKeys(fn (string $slot) => [$slot => $this->backgrounds->forSlot($slot)])
                ->all(),
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function update(BackgroundsFormRequest $request): RedirectResponse
    {
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

        $this->alert->success(trans('admin/design.backgrounds.saved'))->flash();

        return redirect()->route('admin.design.backgrounds');
    }
}
