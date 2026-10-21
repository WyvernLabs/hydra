<?php

namespace Pterodactyl\Http\Controllers\Admin\Users;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\BanTemplate;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

/**
 * Hydra: templates for bans (reason and duration), under Users > Ban templates.
 */
class BanTemplateController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.users.ban-templates', [
            'templates' => BanTemplate::query()->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BanTemplate::query()->create($this->values($request) + [
            'sort' => (int) BanTemplate::query()->max('sort') + 1,
        ]);
        $this->alert->success(__('admin/sanctions.templates.saved'))->flash();

        return redirect()->route('admin.users.ban-templates');
    }

    public function update(Request $request, BanTemplate $template): RedirectResponse
    {
        $template->update($this->values($request));
        $this->alert->success(__('admin/sanctions.templates.saved'))->flash();

        return redirect()->route('admin.users.ban-templates');
    }

    public function delete(BanTemplate $template): RedirectResponse
    {
        $template->delete();
        $this->alert->success(__('admin/sanctions.templates.deleted'))->flash();

        return redirect()->route('admin.users.ban-templates');
    }

    private function values(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|between:1,191',
            'reason' => 'required|string|max:2000',
            'duration_days' => 'nullable|integer|between:1,3650',
        ]);

        return ['name' => $data['name'], 'reason' => $data['reason'], 'duration_days' => $data['duration_days'] ?? null];
    }
}
