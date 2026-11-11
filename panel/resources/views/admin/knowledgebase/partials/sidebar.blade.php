{{-- Hydra: sidebar of the knowledgebase in the admin area. Expects $tree, optionally $post and $category. --}}
@php($kbCanEdit = Auth::user()->hasAdminPermission('knowledgebase.update'))
<aside class="hydra-sidebar hydra-designer-sidebar hydra-kb-sidebar" id="hydraSidebar">
    <div class="hydra-designer-brand">
        <span class="hydra-designer-brand-icon"><x-hydra.icon name="book-open" /></span>
        <span>{{ __('admin/knowledgebase.title') }}</span>
    </div>
    <a href="{{ route('admin.index') }}" class="hydra-designer-back">
        <x-hydra.icon name="arrow-left" />
        <span>{{ __('admin/knowledgebase.back') }}</span>
    </a>
    <div class="hydra-kb-sidebar-actions">
        <a href="{{ route('admin.knowledgebase') }}" class="hydra-nav-item @if(request()->routeIs('admin.knowledgebase')) active @endif">
            <x-hydra.icon name="layout-grid" />
            <span>{{ __('admin/knowledgebase.overview') }}</span>
        </a>
        @if($kbCanEdit)
            <a href="{{ route('admin.knowledgebase.posts.new', isset($category) && $category?->id ? ['category' => $category->id] : []) }}" class="hydra-nav-item @if(request()->routeIs('admin.knowledgebase.posts.new')) active @endif">
                <x-hydra.icon name="file-plus" />
                <span>{{ __('admin/knowledgebase.new_post') }}</span>
            </a>
        @endif
    </div>
    <div class="hydra-nav-title hydra-kb-sidebar-title">{{ __('admin/knowledgebase.categories') }}</div>
    <div class="hydra-kb-sidebar-tree">
        @include('partials.hydra.kb-tree', [
            'tree' => $tree,
            'postUrl' => fn ($p) => route('admin.knowledgebase.post', $p->id),
            'categoryUrl' => fn ($c) => route('admin.knowledgebase.category', $c->id),
            'activePost' => $post ?? null,
            'activeCategory' => $category ?? null,
            'storageKey' => 'hydra:kb-admin',
        ])
    </div>
    <div class="hydra-sidebar-footer">
        <a href="{{ $addon->url() }}" target="_blank" rel="noopener" class="hydra-icon-btn" data-toggle="tooltip" data-placement="top" title="{{ __('admin/knowledgebase.open_public') }}">
            <x-hydra.icon name="external-link" />
        </a>
        <a href="{{ route('admin.index') }}" class="hydra-icon-btn" data-toggle="tooltip" data-placement="top" title="{{ __('admin/knowledgebase.back') }}">
            <x-hydra.icon name="house" />
        </a>
    </div>
</aside>
