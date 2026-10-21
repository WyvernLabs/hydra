{{-- Hydra: tabs of a node, $active is the key of the open tab. --}}
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
@foreach([
    ['about', 'admin.nodes.view', null],
    ['settings', 'admin.nodes.view.settings', 'nodes.update'],
    ['configuration', 'admin.nodes.view.configuration', 'nodes.configuration'],
    ['allocation', 'admin.nodes.view.allocation', 'nodes.allocations'],
    ['servers', 'admin.nodes.view.servers', null],
    ['database', 'admin.nodes.view.database', 'nodes.database'],
    ['firewall', 'admin.nodes.view.firewall', 'nodes.firewall'],
] as [$key, $route, $permission])
@if(is_null($permission) || Auth::user()->hasAdminPermission($permission))
                <li @if($active === $key) class="active" @endif><a href="{{ route($route, $node->id) }}">{{ __('admin/nodes.tabs.' . $key) }}</a></li>
@endif
@endforeach
            </ul>
        </div>
    </div>
</div>
