<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\LoaderDesignService;

/**
 * Hydra: loading animation of the panel (Designer > Loading animation).
 */
class LoaderDesignController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private LoaderDesignService $loader)
    {
    }

    public function index(): View
    {
        return view('admin.design.loader', [
            'style' => (string) config('hydra.loader.style', 'ring'),
            'speed' => $this->loader->speed(),
            'gifUrl' => $this->loader->gifUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'style' => ['required', 'string', Rule::in(LoaderDesignService::STYLES)],
            'speed' => 'required|integer|between:' . implode(',', LoaderDesignService::SPEED),
            'gif' => 'nullable|file|max:2048|mimes:gif,webp,png,svg',
            'remove_gif' => 'nullable|boolean',
        ], [], [
            'style' => __('admin/design.loader.style'),
            'speed' => __('admin/design.loader.speed'),
            'gif' => __('admin/design.loader.gif'),
        ]);

        $gif = $request->file('gif');
        $removeGif = !$gif && $request->boolean('remove_gif');
        // A GIF style needs a GIF, either a new one or the one stored before.
        if ($data['style'] === 'gif' && !$gif && ($removeGif || !$this->loader->hasGif())) {
            return redirect()->route('admin.design.loader')
                ->withInput()
                ->withErrors(['gif' => __('admin/design.loader.gif_missing')]);
        }

        $this->loader->save($data['style'], (int) $data['speed'], $gif, $removeGif);
        $this->alert->success(__('admin/design.loader.saved'))->flash();

        return redirect()->route('admin.design.loader');
    }
}
