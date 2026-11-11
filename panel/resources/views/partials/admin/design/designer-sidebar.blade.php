{{--
    Hydra: sidebar of the designer (all pages under /admin/design). Replaces the
    normal admin navigation; expects $designerTab (key of the open page).
--}}
@php
    $designerUser = Auth::user();
    $designerItems = array_filter([
        ['general', 'admin.design', 'palette', 'design.view'],
        ['backgrounds', 'admin.design.backgrounds', 'image', 'design.view'],
        ['login', 'admin.design.login', 'log-in', 'design.view'],
        ['maintenance', 'admin.design.maintenance', 'construction', 'design.view'],
        ['metadata', 'admin.design.metadata', 'share-2', 'design.view'],
        ['navigation', 'admin.design.navigation', 'list-tree', 'design.view'],
        ['console', 'admin.design.console', 'terminal', 'design.view'],
        ['eggs', 'admin.design.eggs', 'egg', 'design.view'],
        ['effects', 'admin.design.effects', 'sparkles', 'design.view'],
        ['announcements', 'admin.design.announcements', 'megaphone', 'announcements.view'],
        ['links', 'admin.design.links', 'link', 'design.view'],
        ['addons', 'admin.design.addons', 'puzzle', 'addons.view'],
    ], fn ($item) => $designerUser->hasAdminPermission($item[3]));
@endphp
<aside class="hydra-sidebar hydra-designer-sidebar" id="hydraSidebar">
    <div class="hydra-designer-brand">
        <span class="hydra-designer-brand-icon"><x-hydra.icon name="paintbrush" /></span>
        <span>{{ __('admin/design.designer.title') }}</span>
    </div>
    <a href="{{ route('admin.index') }}" class="hydra-designer-back">
        <x-hydra.icon name="arrow-left" />
        <span>{{ __('admin/design.designer.back') }}</span>
    </a>
    <nav class="hydra-nav">
        <div class="hydra-nav-section">
            <div class="hydra-nav-title">{{ __('admin/design.designer.sections') }}</div>
            @foreach($designerItems as [$key, $routeName, $icon])
                <a href="{{ route($routeName) }}" class="hydra-nav-item @if($designerTab === $key) active @endif">
                    <x-hydra.icon :name="$icon" />
                    <span>{{ __("admin/design.tabs.$key") }}</span>
                </a>
            @endforeach
        </div>
    </nav>
    <div class="hydra-sidebar-footer">
        <a href="{{ route('index') }}" class="hydra-icon-btn" data-toggle="tooltip" data-placement="top" title="{{ __('admin/common.nav.exit') }}">
            <x-hydra.icon name="layout-grid" />
        </a>
        <a href="{{ route('admin.index') }}" class="hydra-icon-btn" data-toggle="tooltip" data-placement="top" title="{{ __('admin/design.designer.back') }}">
            <x-hydra.icon name="house" />
        </a>
    </div>
</aside>
