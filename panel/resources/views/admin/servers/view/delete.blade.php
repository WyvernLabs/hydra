@extends('layouts.admin')

@section('title')
    {{ __('admin/servers.page_title_tab', ['name' => $server->name, 'tab' => __('admin/servers.tabs.delete')]) }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ __('admin/servers.delete.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">{{ __('admin/servers.tabs.delete') }}</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/servers.delete.safe') }}</h3>
            </div>
            <div class="box-body">
                <p>{{ __('admin/servers.delete.safe_text') }}</p>
                <p class="text-danger small">{!! __('admin/servers.delete.warning') !!}</p>
            </div>
            <div class="box-footer">
                <form id="deleteform" action="{{ route('admin.servers.view.delete', $server->id) }}" method="POST">
                    {!! csrf_field() !!}
                    <button id="deletebtn" class="btn btn-danger">{{ __('admin/servers.delete.safe_button') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/servers.delete.force') }}</h3>
            </div>
            <div class="box-body">
                <p>{{ __('admin/servers.delete.force_text') }}</p>
                <p class="text-danger small">{!! __('admin/servers.delete.force_warning') !!}</p>
            </div>
            <div class="box-footer">
                <form id="forcedeleteform" action="{{ route('admin.servers.view.delete', $server->id) }}" method="POST">
                    {!! csrf_field() !!}
                    <input type="hidden" name="force_delete" value="1" />
                    <button id="forcedeletebtn" class="btn btn-danger">{{ __('admin/servers.delete.force_button') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#deletebtn').click(function (event) {
        event.preventDefault();
        swal({
            title: '',
            type: 'warning',
            text: @json(__('admin/servers.delete.confirm')),
            showCancelButton: true,
            confirmButtonText: @json(__('admin/common.actions.delete')),
            cancelButtonText: @json(__('admin/common.actions.cancel')),
            confirmButtonColor: '#d9534f',
            closeOnConfirm: false
        }, function () {
            $('#deleteform').submit()
        });
    });

    $('#forcedeletebtn').click(function (event) {
        event.preventDefault();
        swal({
            title: '',
            type: 'warning',
            text: @json(__('admin/servers.delete.confirm')),
            showCancelButton: true,
            confirmButtonText: @json(__('admin/common.actions.delete')),
            cancelButtonText: @json(__('admin/common.actions.cancel')),
            confirmButtonColor: '#d9534f',
            closeOnConfirm: false
        }, function () {
            $('#forcedeleteform').submit()
        });
    });
    </script>
@endsection
