<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BrandingService;
use Pterodactyl\Services\Hydra\MetadataService;

/**
 * Hydra: description and link preview of the panel (Design > Metadata).
 */
class MetadataController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private MetadataService $metadata,
        private BrandingService $branding,
    ) {
    }

    public function index(): View
    {
        return view('admin.design.metadata', [
            'meta' => $this->metadata->values(),
            'imageUrl' => $this->metadata->imageUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:' . MetadataService::MAX_TITLE,
            'description' => 'nullable|string|max:' . MetadataService::MAX_DESCRIPTION,
            'keywords' => 'nullable|string|max:' . MetadataService::MAX_KEYWORDS,
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'use_color' => 'nullable|boolean',
            'large_image' => 'nullable|boolean',
            'indexable' => 'nullable|boolean',
            'image' => 'nullable|file|max:5120|mimes:png,jpg,jpeg,gif,webp',
            'reset_image' => 'nullable|boolean',
        ], [], [
            'title' => __('admin/design.metadata.title_field'),
            'description' => __('admin/design.metadata.description'),
            'keywords' => __('admin/design.metadata.keywords'),
            'color' => __('admin/design.metadata.color'),
            'image' => __('admin/design.metadata.image'),
        ]);

        $this->metadata->save([
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'keywords' => $data['keywords'] ?? '',
            'color' => $request->boolean('use_color') ? ($data['color'] ?? '') : '',
            'large_image' => $request->boolean('large_image'),
            'indexable' => $request->boolean('indexable'),
        ]);

        if ($request->hasFile('image')) {
            $this->branding->store('meta', $request->file('image'));
        } elseif ($request->boolean('reset_image')) {
            $this->branding->reset('meta');
        }

        $this->alert->success(__('admin/design.metadata.saved'))->flash();

        return redirect()->route('admin.design.metadata');
    }
}
