@extends('layouts.admin')

@section('title')
    {{ __('admin/servers.page_title_tab', ['name' => $server->name, 'tab' => __('admin/servers.tabs.manage')]) }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ __('admin/servers.manage.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">{{ __('admin/servers.tabs.manage') }}</li>
    </ol>
@endsection

@section('content')
    @include('admin.servers.partials.navigation')
    <div class="row equal-height">
        @if(Auth::user()->hasAdminPermission('servers.manage'))
        <div class="col-sm-4">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.manage.reinstall') }}</h3>
                </div>
                <div class="box-body">
                    <p>{!! __('admin/servers.manage.reinstall_text') !!}</p>
                </div>
                <div class="box-footer">
                    @if(! $server->canBeReinstalled())
                        <button class="btn btn-danger disabled">{{ __('admin/servers.manage.reinstall') }}</button>
                        <p style="padding-top: 1rem;">{{ __('admin/servers.manage.reinstall_skip') }}</p>
                    @elseif($server->isInstalled())
                        <form action="{{ route('admin.servers.view.manage.reinstall', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-danger">{{ __('admin/servers.manage.reinstall') }}</button>
                        </form>
                    @else
                        <button class="btn btn-danger disabled">{{ __('admin/servers.manage.reinstall_needs_install') }}</button>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.manage.install_status') }}</h3>
                </div>
                <div class="box-body">
                    <p>{{ __('admin/servers.manage.install_status_text') }}</p>
                </div>
                <div class="box-footer">
                    <form action="{{ route('admin.servers.view.manage.toggle', $server->id) }}" method="POST">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-primary">{{ __('admin/servers.manage.toggle_install') }}</button>
                    </form>
                </div>
            </div>
        </div>

        @if(! $server->isSuspended())
            <div class="col-sm-4">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/servers.manage.suspend') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>{{ __('admin/servers.manage.suspend_text') }}</p>
                    </div>
                    <div class="box-footer">
                        <form action="{{ route('admin.servers.view.manage.suspension', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <input type="hidden" name="action" value="suspend" />
                            <button type="submit" class="btn btn-warning @if(! is_null($server->transfer)) disabled @endif">{{ __('admin/servers.manage.suspend') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/servers.manage.unsuspend') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>{{ __('admin/servers.manage.unsuspend_text') }}</p>
                    </div>
                    <div class="box-footer">
                        <form action="{{ route('admin.servers.view.manage.suspension', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            <input type="hidden" name="action" value="unsuspend" />
                            <button type="submit" class="btn btn-success">{{ __('admin/servers.manage.unsuspend') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
        @endif

        @if(Auth::user()->hasAdminPermission('servers.lockdown') && config('hydra.addons.lockdown.enabled'))
            <div class="col-sm-4">
                <div class="box {{ $server->isLockedDown() ? 'box-success' : 'box-danger' }}">
                    <div class="box-header with-border">
                        <h3 class="box-title"><x-hydra.icon name="{{ $server->isLockedDown() ? 'lock-open' : 'lock' }}" /> {{ __('admin/servers.manage.lockdown') }}</h3>
                    </div>
                    <div class="box-body">
                        @if($server->isLockedDown())
                            <p>{{ __('admin/servers.manage.lockdown_active', ['date' => $server->hydra_lockdown_at->locale(app()->getLocale())->isoFormat('LLL')]) }}</p>
                        @else
                            <p>{{ __('admin/servers.manage.lockdown_text') }}</p>
                        @endif
                        @if($server->isSuspended())
                            <p class="text-muted small">{{ __('admin/servers.manage.lockdown_suspended') }}</p>
                        @endif
                    </div>
                    <div class="box-footer">
                        <form action="{{ route('admin.servers.view.manage.lockdown', $server->id) }}" method="POST">
                            {!! csrf_field() !!}
                            @if($server->isLockedDown())
                                <input type="hidden" name="lockdown" value="0" />
                                <button type="submit" class="btn btn-success">{{ __('admin/servers.manage.lockdown_lift') }}</button>
                            @else
                                <input type="hidden" name="lockdown" value="1" />
                                <button type="submit" class="btn btn-danger">{{ __('admin/servers.manage.lockdown_enable') }}</button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if(is_null($server->transfer))
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/servers.manage.transfer') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>
                            {!! __('admin/servers.manage.transfer_text') !!}
                        </p>
                    </div>

                    <div class="box-footer">
                        @if($canTransfer)
                            <button class="btn btn-success" data-toggle="modal" data-target="#transferServerModal">{{ __('admin/servers.manage.transfer') }}</button>
                        @else
                            <button class="btn btn-success disabled">{{ __('admin/servers.manage.transfer') }}</button>
                            <p style="padding-top: 1rem;">{{ __('admin/servers.manage.transfer_needs_nodes') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="col-sm-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/servers.manage.transfer') }}</h3>
                    </div>
                    <div class="box-body">
                        <p>
                            {!! __('admin/servers.manage.transferring', ['date' => e($server->transfer->created_at)]) !!}
                        </p>
                    </div>

                    <div class="box-footer">
                        <button class="btn btn-success disabled">{{ __('admin/servers.manage.transfer') }}</button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="modal fade" id="transferServerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('admin.servers.view.manage.transfer', $server->id) }}" method="POST">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('admin/common.actions.cancel') }}"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">{{ __('admin/servers.manage.transfer') }}</h4>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label for="pNodeId">{{ __('admin/servers.node') }}</label>
                                <select name="node_id" id="pNodeId" class="form-control">
                                    @foreach($locations as $location)
                                        <optgroup label="{{ $location->long }} ({{ $location->short }})">
                                            @foreach($location->nodes as $node)

                                                @if($node->id != $server->node_id)
                                                    <option value="{{ $node->id }}"
                                                            @if($location->id === old('location_id')) selected @endif
                                                    >{{ $node->name }}</option>
                                                @endif

                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <p class="small text-muted no-margin">{{ __('admin/servers.manage.transfer_node_help') }}</p>
                            </div>

                            <div class="form-group col-md-12">
                                <label for="pAllocation">{{ __('admin/servers.fields.default_allocation') }}</label>
                                <select name="allocation_id" id="pAllocation" class="form-control"></select>
                                <p class="small text-muted no-margin">{{ __('admin/servers.fields.default_allocation_help') }}</p>
                            </div>

                            <div class="form-group col-md-12">
                                <label for="pAllocationAdditional">{{ __('admin/servers.fields.additional_allocations') }}</label>
                                <select name="allocation_additional[]" id="pAllocationAdditional" class="form-control" multiple></select>
                                <p class="small text-muted no-margin">{{ __('admin/servers.fields.additional_allocations_help') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        {!! csrf_field() !!}
                        <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">{{ __('admin/common.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-success btn-sm">{{ __('admin/servers.manage.confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/lodash/lodash.js') !!}

    @if($canTransfer)
        {!! Theme::js('js/admin/server/transfer.js') !!}
    @endif
@endsection
