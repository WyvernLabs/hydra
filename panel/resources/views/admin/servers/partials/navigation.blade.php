@php
    /** @var \Pterodactyl\Models\Server $server */
    $router = app('router');
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li class="{{ $router->currentRouteNamed('admin.servers.view') ? 'active' : '' }}">
                    <a href="{{ route('admin.servers.view', $server->id) }}">{{ __('admin/servers.tabs.about') }}</a></li>
                @if($server->isInstalled())
                    @if(Auth::user()->hasAdminPermission('servers.details'))
                    <li class="{{ $router->currentRouteNamed('admin.servers.view.details') ? 'active' : '' }}">
                        <a href="{{ route('admin.servers.view.details', $server->id) }}">{{ __('admin/servers.tabs.details') }}</a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAdminPermission('servers.build'))
                    <li class="{{ $router->currentRouteNamed('admin.servers.view.build') ? 'active' : '' }}">
                        <a href="{{ route('admin.servers.view.build', $server->id) }}">{{ __('admin/servers.tabs.build') }}</a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAdminPermission('servers.startup'))
                    <li class="{{ $router->currentRouteNamed('admin.servers.view.startup') ? 'active' : '' }}">
                        <a href="{{ route('admin.servers.view.startup', $server->id) }}">{{ __('admin/servers.tabs.startup') }}</a>
                    </li>
                    @endif
                    @if(Auth::user()->hasAdminPermission('servers.databases'))
                    <li class="{{ $router->currentRouteNamed('admin.servers.view.database') ? 'active' : '' }}">
                        <a href="{{ route('admin.servers.view.database', $server->id) }}">{{ __('admin/servers.tabs.database') }}</a>
                    </li>
                    @endif
                @endif
                @if(Auth::user()->hasAdminPermission('servers.manage') || Auth::user()->hasAdminPermission('servers.transfer') || Auth::user()->hasAdminPermission('servers.lockdown'))
                <li class="{{ $router->currentRouteNamed('admin.servers.view.manage') ? 'active' : '' }}">
                    <a href="{{ route('admin.servers.view.manage', $server->id) }}">{{ __('admin/servers.tabs.manage') }}</a>
                </li>
                @endif
                @if(Auth::user()->hasAdminPermission('servers.delete'))
                <li class="tab-danger {{ $router->currentRouteNamed('admin.servers.view.delete') ? 'active' : '' }}">
                    <a href="{{ route('admin.servers.view.delete', $server->id) }}">{{ __('admin/servers.tabs.delete') }}</a>
                </li>
                @endif
                <li class="tab-success">
                    <a href="/server/{{ $server->uuidShort }}" target="_blank"><i class="fa fa-external-link"></i></a>
                </li>
            </ul>
        </div>
    </div>
</div>
