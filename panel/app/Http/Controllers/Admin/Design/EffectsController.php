<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Effects\SeasonalEffects;

/**
 * Hydra: picks the seasonal event of the panel.
 */
class EffectsController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private SeasonalEffects $effects)
    {
    }

    public function index(): View
    {
        return view('admin.design.effects', [
            'current' => $this->effects->event(),
            'events' => array_merge([SeasonalEffects::OFF], array_keys(SeasonalEffects::EVENTS)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', Rule::in(array_merge([SeasonalEffects::OFF], array_keys(SeasonalEffects::EVENTS)))],
        ]);

        $this->effects->setEvent($data['event']);
        $this->alert->success(__('admin/design.effects.saved'))->flash();

        return redirect()->route('admin.design.effects');
    }
}
