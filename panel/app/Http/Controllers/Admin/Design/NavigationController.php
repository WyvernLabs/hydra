<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\ServerNavigation;
use Pterodactyl\Traits\Helpers\AvailableLanguages;

/**
 * Hydra: categories, buttons, icons and colors of the server navigation
 * (Design > Navigation).
 */
class NavigationController extends Controller
{
    use AvailableLanguages;

    public function __construct(private AlertsMessageBag $alert, private ServerNavigation $navigation)
    {
    }

    public function index(): View
    {
        return view('admin.design.navigation', [
            'navigation' => $this->navigation->get(),
            'buttons' => collect(ServerNavigation::BUTTONS)
                ->map(fn (string $icon, string $key) => ['label' => trans("client/common.routes.$key"), 'icon' => $icon])
                ->all(),
            'eggs' => Egg::query()->with('nest')->orderBy('nest_id')->orderBy('name')->get()
                ->map(fn (Egg $egg) => ['id' => $egg->id, 'name' => $egg->name, 'nest' => $egg->nest->name])
                ->all(),
            'languages' => $this->getAvailableLanguages(true),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request): RedirectResponse
    {
        if ($request->boolean('reset')) {
            $this->navigation->reset();
            $this->alert->success(trans('admin/design.navigation.reset_done'))->flash();

            return redirect()->route('admin.design.navigation');
        }

        $request->validate(['navigation' => 'required|string|max:65535']);
        $this->navigation->save((array) json_decode($request->input('navigation'), true));

        $this->alert->success(trans('admin/design.navigation.saved'))->flash();

        return redirect()->route('admin.design.navigation');
    }
}
