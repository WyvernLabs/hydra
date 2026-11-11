<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Hydra\MaintenancePageService;

/**
 * Hydra: the "503 Service Unavailable" page shown during updates
 * (Design > Maintenance page).
 */
class MaintenancePageController extends Controller
{
    use AvailableLanguages;

    public function __construct(private AlertsMessageBag $alert, private MaintenancePageService $page)
    {
    }

    public function index(): View
    {
        $languages = $this->getAvailableLanguages(true);
        $values = $this->page->values();

        return view('admin.design.maintenance', [
            'languages' => $languages,
            'values' => $values,
            'defaults' => collect(array_keys($languages))->mapWithKeys(fn ($code) => [$code => MaintenancePageService::defaults($code)])->all(),
            'background' => ['image' => $values['image'], 'blur' => $values['blur'], 'dim' => $values['dim']],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(array_merge([
            'title' => 'nullable|array',
            'title.*' => 'nullable|string|max:' . MaintenancePageService::MAX_TITLE,
            'description' => 'nullable|array',
            'description.*' => 'nullable|string|max:' . MaintenancePageService::MAX_DESCRIPTION,
            'logo' => 'nullable|boolean',
            'reset_background' => 'nullable|string',
        ], BackgroundService::rules('bg')), [], [
            'title.*' => __('admin/design.maintenance.heading'),
            'description.*' => __('admin/design.maintenance.description'),
        ]);

        $this->page->save([
            'title' => (array) $request->input('title', []),
            'description' => (array) $request->input('description', []),
            'logo' => $request->boolean('logo'),
            'blur' => (int) $request->input('bg_blur', 0),
            'dim' => (int) $request->input('bg_dim', 0),
        ]);

        if ($request->filled('reset_background')) {
            $this->page->resetImage();
        } elseif ($request->hasFile('bg_image')) {
            $this->page->storeImage($request->file('bg_image'));
        }

        $this->alert->success(__('admin/design.maintenance.saved'))->flash();

        return redirect()->route('admin.design.maintenance');
    }

    /**
     * The page exactly as visitors see it, without the automatic reload.
     */
    public function preview(Request $request): Response
    {
        $locale = $request->query('lang');
        $locale = is_string($locale) && array_key_exists($locale, $this->getAvailableLanguages()) ? $locale : null;

        return response()->view('errors.503', ['hydraPreview' => true, 'hydraLocale' => $locale])
            ->header('X-Robots-Tag', 'noindex');
    }
}
