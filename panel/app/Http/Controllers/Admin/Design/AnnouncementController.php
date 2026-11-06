<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Pterodactyl\Models\Node;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\HydraAnnouncement;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Http\Requests\Admin\Design\AnnouncementFormRequest;

/**
 * Hydra: general announcements and announcements per node in the client area.
 */
class AnnouncementController extends Controller
{
    use AvailableLanguages;

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.design.announcements.index', [
            'announcements' => HydraAnnouncement::query()->orderBy('sort')->orderByDesc('id')->get(),
            'nodes' => Node::query()->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('admin.design.announcements.edit', $this->formData(null));
    }

    public function view(HydraAnnouncement $announcement): View
    {
        return view('admin.design.announcements.edit', $this->formData($announcement));
    }

    public function store(AnnouncementFormRequest $request): RedirectResponse
    {
        HydraAnnouncement::query()->create($request->announcementData());
        $this->alert->success(__('admin/announcements.saved'))->flash();

        return redirect()->route('admin.design.announcements');
    }

    public function update(AnnouncementFormRequest $request, HydraAnnouncement $announcement): RedirectResponse
    {
        $announcement->update($request->announcementData());
        $this->alert->success(__('admin/announcements.saved'))->flash();

        return redirect()->route('admin.design.announcements');
    }

    public function delete(HydraAnnouncement $announcement): RedirectResponse
    {
        $announcement->delete();
        $this->alert->success(__('admin/announcements.deleted'))->flash();

        return redirect()->route('admin.design.announcements');
    }

    private function formData(?HydraAnnouncement $announcement): array
    {
        return [
            'announcement' => $announcement,
            'languages' => $this->getAvailableLanguages(true),
            'allNodes' => Node::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
