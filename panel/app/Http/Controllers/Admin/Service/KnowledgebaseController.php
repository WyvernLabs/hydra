<?php

namespace Pterodactyl\Http\Controllers\Admin\Service;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\HydraKbPost;
use Pterodactyl\Models\HydraKbMedia;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\HydraKbCategory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\KnowledgebaseService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Hydra\Addons\KnowledgebaseAddon;

/**
 * Hydra: knowledgebase in the admin area (Service Management > Knowledgebase):
 * categories, subcategories and posts. Needs the addon "knowledgebase".
 */
class KnowledgebaseController extends Controller
{
    use AvailableLanguages;

    public const MAX_NAME = 80;

    public function __construct(
        private AlertsMessageBag $alert,
        private KnowledgebaseService $service,
        private KnowledgebaseAddon $addon,
    ) {
    }

    public function index(): View
    {
        return view('admin.knowledgebase.index', $this->shared() + [
            'counts' => [
                'categories' => HydraKbCategory::query()->whereNull('parent_id')->count(),
                'subcategories' => HydraKbCategory::query()->whereNotNull('parent_id')->count(),
                'posts' => HydraKbPost::query()->count(),
                'drafts' => HydraKbPost::query()->where('published', false)->count(),
            ],
        ]);
    }

    // ------------------------------------------------------------------ categories

    public function category(HydraKbCategory $category): View
    {
        return view('admin.knowledgebase.category', $this->shared() + ['category' => $category->load('parent', 'children', 'posts')]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);
        $category = HydraKbCategory::query()->create($data + [
            'sort' => (int) HydraKbCategory::query()->where('parent_id', $data['parent_id'])->max('sort') + 1,
        ]);

        $this->alert->success(__('admin/knowledgebase.category_created'))->flash();

        return redirect()->route('admin.knowledgebase.category', $category->id);
    }

    public function updateCategory(Request $request, HydraKbCategory $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);
        $category->update($data + ['sort' => (int) $request->input('sort', $category->sort)]);

        $this->alert->success(__('admin/knowledgebase.category_saved'))->flash();

        return redirect()->route('admin.knowledgebase.category', $category->id);
    }

    public function deleteCategory(HydraKbCategory $category): RedirectResponse
    {
        $parent = $category->parent_id;
        $category->delete();

        $this->alert->success(__('admin/knowledgebase.category_deleted'))->flash();

        return $parent ? redirect()->route('admin.knowledgebase.category', $parent) : redirect()->route('admin.knowledgebase');
    }

    // ------------------------------------------------------------------ posts

    public function createPost(Request $request): View
    {
        $post = new HydraKbPost(['category_id' => (int) $request->query('category'), 'published' => true, 'titles' => [], 'contents' => []]);

        return view('admin.knowledgebase.post', $this->shared() + ['post' => $post]);
    }

    public function editPost(HydraKbPost $post): View
    {
        return view('admin.knowledgebase.post', $this->shared() + ['post' => $post]);
    }

    public function storePost(Request $request): RedirectResponse
    {
        $data = $this->validatePost($request);
        $post = HydraKbPost::query()->create($data + [
            'slug' => $this->service->slug($data['titles'][config('app.locale')]),
            'author_id' => $request->user()->id,
            'sort' => (int) HydraKbPost::query()->where('category_id', $data['category_id'])->max('sort') + 1,
        ]);

        $this->alert->success(__('admin/knowledgebase.post_created'))->flash();

        return redirect()->route('admin.knowledgebase.post', $post->id);
    }

    public function updatePost(Request $request, HydraKbPost $post): RedirectResponse
    {
        $data = $this->validatePost($request, $post);
        $slug = trim((string) $request->input('slug'));
        $post->update($data + [
            'slug' => $slug !== '' ? $this->service->slug($slug, $post->id) : $post->slug,
            'sort' => (int) $request->input('sort', $post->sort),
        ]);

        $this->alert->success(__('admin/knowledgebase.post_saved'))->flash();

        return redirect()->route('admin.knowledgebase.post', $post->id);
    }

    public function deletePost(HydraKbPost $post): RedirectResponse
    {
        $category = $post->category_id;
        $post->delete();

        $this->alert->success(__('admin/knowledgebase.post_deleted'))->flash();

        return redirect()->route('admin.knowledgebase.category', $category);
    }

    // ------------------------------------------------------------------ editor

    public function upload(Request $request): array
    {
        $request->validate([
            'image' => ['required', 'file', 'mimetypes:' . implode(',', HydraKbMedia::mimes()), 'max:' . ($this->addon->uploadLimit() * 1024)],
        ]);

        $media = $this->service->upload($request->user(), $request->file('image'));

        return ['uuid' => $media->uuid, 'url' => $media->url(), 'name' => $media->name, 'video' => $media->isVideo()];
    }

    public function preview(Request $request): array
    {
        $data = $request->validate(['body' => 'nullable|string|max:' . HydraKbPost::MAX_CONTENT]);

        return ['html' => $this->service->render($data['body'] ?? '')];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Data every page of the area needs (sidebar tree, languages).
     */
    private function shared(): array
    {
        return [
            'tree' => $this->service->tree(true),
            'languages' => $this->getAvailableLanguages(true),
            'defaultLanguage' => config('app.locale'),
            'addon' => $this->addon,
        ];
    }

    private function validateCategory(Request $request, ?HydraKbCategory $category = null): array
    {
        $default = config('app.locale');
        $request->validate([
            'names' => 'required|array',
            "names.$default" => 'required|string|max:' . self::MAX_NAME,
            'names.*' => 'nullable|string|max:' . self::MAX_NAME,
            // Only one level: the parent has to be a main category, and a category
            // that has subcategories cannot become one itself.
            'parent_id' => ['nullable', 'integer', Rule::exists('hydra_kb_categories', 'id')->whereNull('parent_id')],
            'sort' => 'nullable|integer|min:0|max:100000',
        ], [], ["names.$default" => __('admin/knowledgebase.name')]);

        $parent = $request->input('parent_id') ? (int) $request->input('parent_id') : null;
        if ($category && ($parent === $category->id || ($parent && $category->children()->exists()))) {
            $parent = $category->parent_id;
        }

        return [
            'names' => KnowledgebaseService::texts((array) $request->input('names'), array_keys($this->getAvailableLanguages()), self::MAX_NAME, true),
            'parent_id' => $parent,
        ];
    }

    private function validatePost(Request $request, ?HydraKbPost $post = null): array
    {
        $default = config('app.locale');
        $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('hydra_kb_categories', 'id')],
            'titles' => 'required|array',
            "titles.$default" => 'required|string|max:' . HydraKbPost::MAX_TITLE,
            'titles.*' => 'nullable|string|max:' . HydraKbPost::MAX_TITLE,
            'contents' => 'required|array',
            "contents.$default" => 'required|string|max:' . HydraKbPost::MAX_CONTENT,
            'contents.*' => 'nullable|string|max:' . HydraKbPost::MAX_CONTENT,
            'published' => 'nullable|boolean',
            'slug' => 'nullable|string|max:120',
            'sort' => 'nullable|integer|min:0|max:100000',
        ], [], [
            "titles.$default" => __('admin/knowledgebase.title_field'),
            "contents.$default" => __('admin/knowledgebase.content'),
        ]);

        $languages = array_keys($this->getAvailableLanguages());

        return [
            'category_id' => (int) $request->input('category_id'),
            'titles' => KnowledgebaseService::texts((array) $request->input('titles'), $languages, HydraKbPost::MAX_TITLE, true),
            'contents' => KnowledgebaseService::texts((array) $request->input('contents'), $languages, HydraKbPost::MAX_CONTENT, false),
            'published' => $request->boolean('published'),
        ];
    }
}
