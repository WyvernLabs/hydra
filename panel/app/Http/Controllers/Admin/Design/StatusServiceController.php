<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\HydraStatusService;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Hydra: own applications on the status page (addon "status").
 */
class StatusServiceController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return $this->page(null);
    }

    public function view(HydraStatusService $service): View
    {
        return $this->page($service);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = HydraStatusService::query()->create($this->values($request));
        $this->alert->success(__('admin/status.saved', ['name' => $service->name]))->flash();

        return redirect()->route('admin.design.status');
    }

    public function update(Request $request, HydraStatusService $service): RedirectResponse
    {
        $service->update($this->values($request));
        $this->alert->success(__('admin/status.saved', ['name' => $service->name]))->flash();

        return redirect()->route('admin.design.status');
    }

    public function delete(HydraStatusService $service): RedirectResponse
    {
        DB::table('hydra_status_checks')->where('target', $service->statusKey())->delete();
        $service->delete();
        $this->alert->success(__('admin/status.deleted', ['name' => $service->name]))->flash();

        return redirect()->route('admin.design.status');
    }

    private function page(?HydraStatusService $editing): View
    {
        return view('admin.design.status', [
            'services' => HydraStatusService::query()->orderBy('sort')->orderBy('name')->get(),
            'editing' => $editing,
            'states' => DB::table('hydra_status_checks')
                ->where('hour', '>=', now()->startOfHour()->subHour()->format('Y-m-d H:i:s'))
                ->orderBy('hour')
                ->pluck('online', 'target'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function values(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|between:1,64',
            'type' => 'required|string|in:http,tcp',
            'target' => 'required|string|max:2048',
            'sort' => 'nullable|integer|min:0|max:9999',
        ]);

        $valid = $data['type'] === HydraStatusService::TYPE_HTTP
            ? filter_var($data['target'], FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $data['target'])
            : preg_match('/^(\[[0-9a-f:]+\]|[a-z0-9.-]+):([1-9][0-9]{0,4})$/i', $data['target'], $parts) && (int) $parts[2] <= 65535;

        if (!$valid) {
            throw ValidationException::withMessages(['target' => __('admin/status.target_invalid_' . $data['type'])]);
        }

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'target' => $data['target'],
            'enabled' => $request->boolean('enabled'),
            'sort' => (int) ($data['sort'] ?? 0),
        ];
    }
}
