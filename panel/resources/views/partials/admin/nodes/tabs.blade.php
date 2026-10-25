{{-- Hydra: tabs of a node, $active is the key of the open tab. --}}
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
@foreach([
    ['about', 'admin.nodes.view', null],
    ['settings', 'admin.nodes.view.settings', 'nodes.update'],
    ['configuration', 'admin.nodes.view.configuration', 'nodes.configuration'],
    ['install', 'admin.nodes.view.install', 'nodes.install'],
    ['allocation', 'admin.nodes.view.allocation', 'nodes.allocations'],
    ['servers', 'admin.nodes.view.servers', null],
    ['database', 'admin.nodes.view.database', 'nodes.database'],
    ['firewall', 'admin.nodes.view.firewall', 'nodes.firewall'],
    ['backups', 'admin.nodes.view.backups', 'nodes.backups'],
] as [$key, $route, $permission])
{{-- The backups tab belongs to the addon "sftp_backups". --}}
@if((is_null($permission) || Auth::user()->hasAdminPermission($permission)) && ($key !== 'backups' || app(\Pterodactyl\Services\Hydra\Addons\SftpBackupsAddon::class)->enabled()))
                <li @if($active === $key) class="active" @endif><a href="{{ route($route, $node->id) }}">{{ __('admin/nodes.tabs.' . $key) }}</a></li>
@endif
@endforeach
            </ul>
        </div>
    </div>
</div>
