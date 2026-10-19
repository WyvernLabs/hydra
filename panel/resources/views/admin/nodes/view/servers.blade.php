@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.servers_tab.title', ['name' => $node->name]) }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>{{ __('admin/nodes.servers_tab.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('admin/nodes.title') }}</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">{{ __('admin/nodes.tabs.servers') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ __('admin/nodes.tabs.about') }}</a></li>
@if(Auth::user()->hasAdminPermission('nodes.update'))
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">{{ __('admin/nodes.tabs.settings') }}</a></li>
@endif
@if(Auth::user()->hasAdminPermission('nodes.configuration'))
                <li><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">{{ __('admin/nodes.tabs.configuration') }}</a></li>
@endif
@if(Auth::user()->hasAdminPermission('nodes.allocations'))
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">{{ __('admin/nodes.tabs.allocation') }}</a></li>
@endif
                <li class="active"><a href="{{ route('admin.nodes.view.servers', $node->id) }}">{{ __('admin/nodes.tabs.servers') }}</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.servers_tab.list') }}</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>{{ __('admin/nodes.servers_tab.id') }}</th>
                        <th>{{ __('admin/nodes.servers_tab.server_name') }}</th>
                        <th>{{ __('admin/nodes.servers_tab.owner') }}</th>
                        <th>{{ __('admin/nodes.servers_tab.service') }}</th>
                    </tr>
                    @foreach($servers as $server)
                        <tr data-server="{{ $server->uuid }}">
                            <td><code>{{ $server->uuidShort }}</code></td>
                            <td><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></td>
                            <td><a href="{{ route('admin.users.view', $server->owner_id) }}">{{ $server->user->username }}</a></td>
                            <td>{{ $server->nest->name }} ({{ $server->egg->name }})</td>
                        </tr>
                    @endforeach
                </table>
                @if($servers->hasPages())
                    <div class="box-footer with-border">
                        <div class="col-md-12 text-center">{!! $servers->render() !!}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
