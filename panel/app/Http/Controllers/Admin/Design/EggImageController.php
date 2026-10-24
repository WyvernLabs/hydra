<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BackgroundService;

/**
 * Hydra: pictures of every egg (Design > Eggs): the background of the server
 * pages, the banner of the server dashboard and the picture in the server list.
 */
class EggImageController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private BackgroundService $backgrounds)
    {
    }

    public function index(): View
    {
        return view('admin.design.eggs.index', [
            'nests' => Nest::query()->with(['eggs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            'backgrounds' => $this->backgrounds,
        ]);
    }

    public function view(Egg $egg): View
    {
        return view('admin.design.eggs.view', [
            'egg' => $egg->load('nest'),
            'background' => $this->backgrounds->forEgg($egg),
            'images' => collect(BackgroundService::EGG_IMAGES)
                ->mapWithKeys(fn ($column, string $type) => [$type => $this->backgrounds->imageForEgg($egg, $type)])
                ->all(),
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Throwable
     */
    public function background(Request $request, Egg $egg): RedirectResponse
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

        $this->alert->success(__('admin/design.backgrounds.saved'))->flash();

        return redirect()->route('admin.design.eggs.view', $egg->id);
    }

    /**
     * Banner ("banner") or picture in the server list ("list").
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Throwable
     */
    public function image(Request $request, Egg $egg, string $type): RedirectResponse
    {
        if ($request->boolean('reset')) {
            $this->backgrounds->resetEggImage($egg, $type);
        } else {
            $rules = BackgroundService::rules('image');
            $request->validate(['image_image' => array_merge(['required'], array_slice($rules['image_image'], 1))]);

            $this->backgrounds->saveEggImage($egg, $type, $request->file('image_image'));
        }

        $this->alert->success(__("admin/design.eggs.saved_$type"))->flash();

        return redirect()->route('admin.design.eggs.view', $egg->id);
    }
}
