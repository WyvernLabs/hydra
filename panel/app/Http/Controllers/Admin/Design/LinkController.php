<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\PanelLink;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Hydra\Markdown\MarkdownService;
use Pterodactyl\Http\Requests\Admin\Design\PanelLinkFormRequest;

/**
 * Hydra: links and Markdown pages in the sidebar of the client area.
 */
class LinkController extends Controller
{
    use AvailableLanguages;

    public function __construct(private AlertsMessageBag $alert, private MarkdownService $markdown)
    {
    }

    public function index(): View
    {
        return view('admin.design.links.index', [
            'links' => PanelLink::query()->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.design.links.edit', $this->formData(null));
    }

    public function view(PanelLink $link): View
    {
        return view('admin.design.links.edit', $this->formData($link));
    }

    public function store(PanelLinkFormRequest $request): RedirectResponse
    {
        $link = PanelLink::query()->create($request->linkData());
        $this->alert->success(__('admin/design.links.saved'))->flash();

        return redirect()->route('admin.design.links.view', $link->id);
    }

    public function update(PanelLinkFormRequest $request, PanelLink $link): RedirectResponse
    {
        $link->update($request->linkData());
        $this->alert->success(__('admin/design.links.saved'))->flash();

        return redirect()->route('admin.design.links.view', $link->id);
    }

    public function delete(PanelLink $link): RedirectResponse
    {
        $link->delete();
        $this->alert->success(__('admin/design.links.deleted'))->flash();

        return redirect()->route('admin.design.links');
    }

    /**
     * Renders Markdown for the preview next to the editor.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate(['content' => 'nullable|string|max:65535']);

        return new JsonResponse(['html' => $this->markdown->render($request->input('content'))]);
    }

    private function formData(?PanelLink $link): array
    {
        return [
            'link' => $link,
            'languages' => $this->getAvailableLanguages(true),
        ];
    }
}
