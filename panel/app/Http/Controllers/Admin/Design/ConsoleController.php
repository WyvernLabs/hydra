<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\ConsoleSettings;
use Pterodactyl\Traits\Helpers\AvailableLanguages;

/**
 * Hydra: texts of the server console (Design > Console).
 */
class ConsoleController extends Controller
{
    use AvailableLanguages;

    public function __construct(private AlertsMessageBag $alert, private ConsoleSettings $console)
    {
    }

    public function index(): View
    {
        return view('admin.design.console', [
            'console' => $this->console,
            'languages' => $this->getAvailableLanguages(true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $languages = array_keys($this->getAvailableLanguages());
        $data = $request->validate([
            'offline_message' => 'nullable|array',
            'offline_message.*' => 'nullable|string|max:10000',
            'prefix' => 'nullable|string|max:64',
            'daemon_prefix' => 'nullable|string|max:64',
        ]);

        $this->console->save(
            array_intersect_key($data['offline_message'] ?? [], array_flip($languages)),
            $data['prefix'] ?? null,
            $data['daemon_prefix'] ?? null,
        );

        $this->alert->success(__('admin/design.console.saved'))->flash();

        return redirect()->route('admin.design.console');
    }
}
