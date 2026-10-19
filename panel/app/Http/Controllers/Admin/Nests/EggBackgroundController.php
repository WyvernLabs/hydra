<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Pterodactyl\Models\Egg;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BackgroundService;

/**
 * Hydra: background of the server pages of an egg.
 */
class EggBackgroundController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private BackgroundService $backgrounds)
    {
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Throwable
     */
    public function update(Request $request, Egg $egg): RedirectResponse
    {
        if ($request->boolean('reset_background')) {
            $this->backgrounds->resetEgg($egg);
        } else {
            $request->validate(BackgroundService::rules('bg_egg'));

            $this->backgrounds->saveEgg(
                $egg,
                $request->file('bg_egg_image'),
                (int) $request->input('bg_egg_blur', 0),
                (int) $request->input('bg_egg_dim', 0),
            );
        }

        $this->alert->success(__('admin/settings.backgrounds.saved'))->flash();

        return redirect()->route('admin.nests.egg.view', $egg->id);
    }
}
