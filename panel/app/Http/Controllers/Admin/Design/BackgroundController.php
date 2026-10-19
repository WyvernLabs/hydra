<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Http\Requests\Admin\Design\BackgroundsFormRequest;

/**
 * Hydra: backgrounds of the login page and the dashboard, and an overview of
 * the backgrounds of all eggs (Design > Backgrounds).
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
            'eggs' => Egg::query()->with('nest')->orderBy('nest_id')->orderBy('name')->get()
                ->map(fn (Egg $egg) => ['egg' => $egg, 'background' => $this->backgrounds->forEgg($egg)]),
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
