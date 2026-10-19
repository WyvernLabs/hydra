@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.configuration.title', ['name' => $node->name]) }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>{{ __('admin/nodes.configuration.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('admin/nodes.title') }}</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">{{ __('admin/nodes.tabs.configuration') }}</li>
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
                <li class="active"><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">{{ __('admin/nodes.tabs.configuration') }}</a></li>
@endif
@if(Auth::user()->hasAdminPermission('nodes.allocations'))
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">{{ __('admin/nodes.tabs.allocation') }}</a></li>
@endif
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">{{ __('admin/nodes.tabs.servers') }}</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.configuration.file') }}</h3>
            </div>
            <div class="box-body">
                <pre class="no-margin">{{ $node->getYamlConfiguration() }}</pre>
            </div>
            <div class="box-footer">
                <p class="no-margin">{!! __('admin/nodes.configuration.file_help') !!}</p>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.configuration.auto_deploy') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">{{ __('admin/nodes.configuration.auto_deploy_help') }}</p>
            </div>
            <div class="box-footer">
                <button type="button" id="configTokenBtn" class="btn btn-sm btn-default" style="width:100%;">{{ __('admin/nodes.configuration.generate') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#configTokenBtn').on('click', function (event) {
        $.ajax({
            method: 'POST',
            url: '{{ route('admin.nodes.view.configuration.token', $node->id) }}',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).done(function (data) {
            swal({
                type: 'success',
                title: @json(__('admin/nodes.configuration.token_created')),
                text: '<p>' + @json(__('admin/nodes.configuration.token_command')) + '<br /><small><pre>sudo hydra-wings configure --panel-url {{ config('app.url') }} --token ' + data.token + ' --node ' + data.node + '{{ config('app.debug') ? ' --allow-insecure' : '' }}</pre></small></p>',
                html: true
            })
        }).fail(function () {
            swal({
                title: @json(__('admin/common.error')),
                text: @json(__('admin/nodes.configuration.token_error')),
                type: 'error'
            });
        });
    });
    </script>
@endsection
