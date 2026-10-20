<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Addons\Addon;
use Pterodactyl\Services\Hydra\Addons\AddonManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: the addons of the panel (Design > Addons). Every addon is a box that
 * can be switched on and off; a click on the box opens its settings.
 */
class AddonController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private AddonManager $manager)
    {
    }

    public function index(): View
    {
        return view('admin.design.addons.index', ['addons' => AddonManager::all()]);
    }

    public function view(string $addon): View
    {
        return view('admin.design.addons.view', ['addon' => $this->find($addon)]);
    }

    public function update(Request $request, string $addon): RedirectResponse
    {
        $addon = $this->find($addon);
        $values = $request->validate($addon->rules() + array_fill_keys($addon->booleans(), 'nullable|boolean'));

        $this->manager->save($addon, $values);

        if ($request->boolean('enabled') && !is_null($missing = $addon->missing())) {
            $this->manager->setEnabled($addon, false);
            $this->alert->warning(trans('admin/addons.saved_not_enabled', ['reason' => $missing]))->flash();
        } else {
            $this->manager->setEnabled($addon, $request->boolean('enabled'));
            $this->alert->success(trans('admin/addons.saved', ['name' => $addon->name()]))->flash();
        }

        return redirect()->route('admin.design.addons.view', $addon->key());
    }

    /**
     * Switches an addon on or off from the overview.
     */
    public function toggle(Request $request, string $addon): RedirectResponse
    {
        $addon = $this->find($addon);
        $enable = $request->boolean('enabled');

        if ($enable && !is_null($missing = $addon->missing())) {
            $this->alert->warning(trans('admin/addons.cannot_enable', ['reason' => $missing]))->flash();

            return redirect()->route('admin.design.addons.view', $addon->key());
        }

        $this->manager->setEnabled($addon, $enable);
        $this->alert->success(trans($enable ? 'admin/addons.enabled' : 'admin/addons.disabled', ['name' => $addon->name()]))->flash();

        return redirect()->route('admin.design.addons');
    }

    private function find(string $key): Addon
    {
        return AddonManager::find($key) ?? throw new NotFoundHttpException();
    }
}
