@extends('layouts.admin')

@php($admin = Auth::user())

@section('title')
    {{ __('admin/support.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/support.title') }}<small>{{ __('admin/support.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/support.title') }}</li>
    </ol>
@endsection

@section('content')
    {{-- Search in the middle of the page; hidden while a server is shown. --}}
    <div class="row" id="hydraSupportSearch" @if($active && count($tabs) > 0) hidden @endif>
        <div class="col-md-8 col-md-offset-2 col-lg-6 col-lg-offset-3">
            <div class="box hydra-support-search">
                <div class="box-body">
                    <div class="hydra-support-search-icon"><x-hydra.icon name="headset" /></div>
                    <h3>{{ __('admin/support.search_title') }}</h3>
                    <p class="text-muted">{{ __('admin/support.search_help') }}</p>
                    <form action="{{ route('admin.support') }}" method="POST" class="hydra-support-search-form">
                        {!! csrf_field() !!}
                        <input type="text" name="server" class="form-control" value="{{ old('server') }}" placeholder="{{ __('admin/support.search_placeholder') }}" autocomplete="off" spellcheck="false" required autofocus>
                        <button type="submit" class="btn btn-primary">{{ __('admin/support.search_button') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if(count($tabs) > 0)
        <div class="row">
            <div class="col-xs-12">
                <div class="nav-tabs-custom hydra-support" id="hydraSupport" data-active="{{ $active }}">
                    <ul class="nav nav-tabs">
                        @foreach($tabs as $tab)
                            @php($server = $tab['server'])
                            <li data-tab="{{ $server->id }}">
                                <a href="{{ route('admin.support', ['tab' => $server->id]) }}" data-tab-link="{{ $server->id }}">
                                    <span class="hydra-support-dot" data-dot></span>
                                    <span class="hydra-support-tab-name">{{ $server->name }}</span>
                                    <small class="text-muted" data-countdown data-expires="{{ $tab['expires']->toIso8601String() }}"></small>
                                </a>
                                <button type="button" class="hydra-support-tab-close" data-close="{{ route('admin.support.close', $server->id) }}" title="{{ __('admin/support.close') }}" aria-label="{{ __('admin/support.close') }}">&times;</button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content">
                        @foreach($tabs as $tab)
                            @php($server = $tab['server'])
                            <div class="hydra-support-pane" data-pane="{{ $server->id }}" hidden
                                 data-websocket="{{ route('admin.support.websocket', $server->id) }}"
                                 data-expires="{{ $tab['expires']->toIso8601String() }}"
                                 data-cpu-limit="{{ (int) $server->cpu }}"
                                 data-memory-limit="{{ (int) $server->memory * 1024 * 1024 }}"
                                 data-disk-limit="{{ (int) $server->disk * 1024 * 1024 }}">
                                <div class="hydra-support-head">
                                    <div class="hydra-support-title">
                                        <h3>{{ $server->name }}</h3>
                                        <p>
                                            <code>{{ $server->uuidShort }}</code>
                                            <span class="hydra-support-state" data-state>{{ __('admin/support.states.connecting') }}</span>
                                            @if($server->isSuspended())
                                                <span class="label label-danger">{{ __('admin/support.suspended') }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <dl class="hydra-support-facts">
                                        <div><dt>{{ __('admin/support.owner') }}</dt><dd>{{ $server->user->username ?? '–' }}</dd></div>
                                        <div><dt>{{ __('admin/support.node') }}</dt><dd>{{ $server->node->name }}</dd></div>
                                        <div><dt>{{ __('admin/support.address') }}</dt><dd><code>{{ $server->allocation ? $server->allocation->alias . ':' . $server->allocation->port : '–' }}</code></dd></div>
                                        <div><dt>{{ __('admin/support.egg') }}</dt><dd>{{ $server->egg->name ?? '–' }}</dd></div>
                                    </dl>
                                </div>

                                <div class="hydra-support-usage">
                                    <div class="hydra-support-stat"><span>{{ __('admin/support.usage.cpu') }}</span><strong data-stat="cpu">–</strong><small data-limit="cpu">{{ $server->cpu > 0 ? $server->cpu . ' %' : __('admin/support.usage.unlimited') }}</small><i data-bar="cpu"></i></div>
                                    <div class="hydra-support-stat"><span>{{ __('admin/support.usage.memory') }}</span><strong data-stat="memory">–</strong><small data-limit="memory"></small><i data-bar="memory"></i></div>
                                    <div class="hydra-support-stat"><span>{{ __('admin/support.usage.disk') }}</span><strong data-stat="disk">–</strong><small data-limit="disk"></small><i data-bar="disk"></i></div>
                                    <div class="hydra-support-stat"><span>{{ __('admin/support.usage.network') }}</span><strong data-stat="network">–</strong><small data-stat="network-total"></small></div>
                                    <div class="hydra-support-stat"><span>{{ __('admin/support.usage.uptime') }}</span><strong data-stat="uptime">–</strong></div>
                                </div>

                                @if($canControl)
                                    <div class="hydra-support-power">
                                        <button type="button" class="btn btn-sm hydra-power-btn" style="--power-color: #00ff00" data-power="start">{{ __('admin/support.power.start') }}</button>
                                        <button type="button" class="btn btn-sm hydra-power-btn" style="--power-color: #ffff00" data-power="restart">{{ __('admin/support.power.restart') }}</button>
                                        <button type="button" class="btn btn-sm hydra-power-btn" style="--power-color: #ff0000" data-power="stop">{{ __('admin/support.power.stop') }}</button>
                                        <button type="button" class="btn btn-sm hydra-power-btn" style="--power-color: #ff0000" data-power="kill" hidden>{{ __('admin/support.power.kill') }}</button>
                                    </div>
                                @endif

                                <div class="hydra-support-console-wrap">
                                    <div class="hydra-support-error" data-error hidden>
                                        <span data-error-text></span>
                                        <button type="button" class="btn btn-xs btn-default" data-reconnect>{{ __('admin/support.reconnect') }}</button>
                                    </div>
                                    <div class="hydra-support-console" data-console aria-live="off"></div>
                                    @if($canControl)
                                        <form class="hydra-support-command" data-command-form>
                                            <span>$</span>
                                            <input type="text" data-command placeholder="{{ __('admin/support.command_placeholder') }}" autocomplete="off" spellcheck="false">
                                        </form>
                                    @else
                                        <p class="text-muted small hydra-support-viewonly">{{ __('admin/support.view_only') }}</p>
                                    @endif
                                </div>

                                @if($can['suspend'] || $can['reinstall'] || $can['transfer'])
                                    {{-- Hydra: suspend, reinstall and transfer, each with its own permission. --}}
                                    <div class="hydra-support-manage">
                                        <span class="hydra-support-manage-title">{{ __('admin/support.manage') }}</span>
                                        @if(!is_null($server->transfer))
                                            <span class="label label-warning">{{ __('admin/support.transfer_running') }}</span>
                                        @else
                                            @if($can['suspend'])
                                                @if($server->isSuspended())
                                                    <button type="button" class="btn btn-sm btn-default" data-action="unsuspend" data-url="{{ route('admin.support.suspension', $server->id) }}" data-confirm="{{ __('admin/support.confirm_unsuspend', ['name' => $server->name]) }}"><x-hydra.icon name="lock-open" /> {{ __('admin/support.unsuspend') }}</button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-default" data-action="suspend" data-url="{{ route('admin.support.suspension', $server->id) }}" data-confirm="{{ __('admin/support.confirm_suspend', ['name' => $server->name]) }}"><x-hydra.icon name="lock" /> {{ __('admin/support.suspend') }}</button>
                                                @endif
                                            @endif
                                            @if($can['reinstall'])
                                                <button type="button" class="btn btn-sm btn-default" data-action="reinstall" data-url="{{ route('admin.support.reinstall', $server->id) }}" data-confirm="{{ __('admin/support.confirm_reinstall', ['name' => $server->name]) }}"><x-hydra.icon name="rotate-ccw" /> {{ __('admin/support.reinstall') }}</button>
                                            @endif
                                            @if($can['transfer'])
                                                <button type="button" class="btn btn-sm btn-default" data-transfer-toggle><x-hydra.icon name="arrow-right-left" /> {{ __('admin/support.transfer') }}</button>
                                            @endif
                                        @endif
                                        <span class="hydra-support-manage-message" data-manage-message></span>
                                    </div>
                                    @if($can['transfer'] && is_null($server->transfer))
                                        <form class="hydra-support-transfer" data-transfer-form data-url="{{ route('admin.support.transfer', $server->id) }}" data-node="{{ $server->node_id }}" hidden>
                                            <h4>{{ __('admin/support.transfer_title') }}</h4>
                                            <p class="text-muted small">{{ __('admin/support.transfer_help') }}</p>
                                            <p class="text-muted small" data-transfer-empty hidden>{{ __('admin/support.transfer_no_nodes') }}</p>
                                            <div class="row" data-transfer-fields>
                                                <div class="form-group col-sm-4">
                                                    <label class="control-label">{{ __('admin/support.transfer_node') }}</label>
                                                    <select class="form-control" data-transfer-node></select>
                                                </div>
                                                <div class="form-group col-sm-4">
                                                    <label class="control-label">{{ __('admin/support.transfer_allocation') }}</label>
                                                    <select class="form-control" data-transfer-allocation></select>
                                                </div>
                                                <div class="form-group col-sm-4">
                                                    <label class="control-label">{{ __('admin/support.transfer_additional') }}</label>
                                                    <select class="form-control" multiple size="3" data-transfer-additional></select>
                                                    <p class="text-muted small no-margin">{{ __('admin/support.transfer_additional_help', ['count' => max(0, $server->allocations->count() - 1)]) }}</p>
                                                </div>
                                            </div>
                                            <div class="hydra-support-transfer-buttons">
                                                <button type="button" class="btn btn-sm btn-default" data-transfer-toggle>{{ __('admin/support.cancel') }}</button>
                                                <button type="submit" class="btn btn-sm btn-primary" data-transfer-submit>{{ __('admin/support.transfer_start') }}</button>
                                            </div>
                                        </form>
                                    @endif
                                @endif

                                <div class="hydra-support-actions">
                                    @if($admin->hasAdminPermission('servers.view'))
                                        <a href="{{ route('admin.servers.view', $server->id) }}" class="btn btn-sm btn-default"><x-hydra.icon name="server" /> {{ __('admin/support.manage_server') }}</a>
                                    @endif
                                    @if($server->user && $admin->hasAdminPermission('users.view'))
                                        <a href="{{ route('admin.users.view', $server->user->id) }}" class="btn btn-sm btn-default"><x-hydra.icon name="user-cog" /> {{ __('admin/support.manage_owner') }}</a>
                                    @endif
                                    {{-- Back to the search, on the other side of the row. --}}
                                    <button type="button" class="btn btn-sm btn-default hydra-support-closeview" data-close-view>
                                        <x-hydra.icon name="x" /> {{ __('admin/support.close_view') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="text-muted small">{{ __('admin/support.tabs_help') }}</p>
            </div>
        </div>
    @endif
@endsection

@section('footer-scripts')
    @parent
    @if(count($tabs) > 0)
        <script>
            window.HydraSupport = {
                csrf: @json(csrf_token()),
                locale: @json(str_replace('_', '-', app()->getLocale())),
                text: {
                    states: @json(__('admin/support.states')),
                    closesIn: @json(__('admin/support.closes_in')),
                    unlimited: @json(__('admin/support.usage.unlimited')),
                    connectionError: @json(__('admin/support.connection_error')),
                    expired: @json(__('admin/support.expired')),
                },
                // Other nodes with free allocations, for the transfer.
                transferNodes: @json($transferNodes),
            };
        </script>
        <script src="/hydra/support.js?v={{ substr(md5_file(public_path('hydra/support.js')), 0, 8) }}"></script>
    @endif
@endsection
