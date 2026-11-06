@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'addons'])

@php
    $canSave = Auth::user()->hasAdminPermission('addons.update');
    $sent = !is_null(old('_token'));
    $enabled = $sent ? (bool) old('enabled') : ($editing->enabled ?? true);
    $selectedType = old('type', $editing->type ?? 'http');
@endphp

@section('title')
    {{ __('admin/status.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/status.title') }}<small>{{ __('admin/status.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design.addons') }}">{{ __('admin/addons.title') }}</a></li>
        <li><a href="{{ route('admin.design.addons.view', 'status') }}">{{ __('admin/addons.status.name') }}</a></li>
        <li class="active">{{ __('admin/status.title') }}</li>
    </ol>
@endsection

@section('content')
@yield('design::nav')
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/status.list') }}</h3>
                <div class="box-tools">
                    <a href="{{ route('status') }}" target="_blank" class="btn btn-sm btn-default">{{ __('admin/status.open_page') }}</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>{{ __('admin/status.name') }}</th>
                        <th>{{ __('admin/status.type') }}</th>
                        <th>{{ __('admin/status.target') }}</th>
                        <th class="text-center">{{ __('admin/status.state') }}</th>
                        <th></th>
                    </tr>
                    @forelse($services as $service)
                        <tr>
                            <td><a href="{{ route('admin.design.status.view', $service->id) }}">{{ $service->name }}</a></td>
                            <td>{{ __('admin/status.types.' . $service->type) }}</td>
                            <td><code>{{ \Illuminate\Support\Str::limit($service->target, 48) }}</code></td>
                            <td class="text-center">
                                @if(!$service->enabled)
                                    <span class="label label-default">{{ __('admin/common.disabled') }}</span>
                                @elseif(!$states->has($service->statusKey()))
                                    <span class="label label-default">{{ __('client/status.states.unknown') }}</span>
                                @elseif($states[$service->statusKey()])
                                    <span class="label label-success">{{ __('client/status.states.online') }}</span>
                                @else
                                    <span class="label label-danger">{{ __('client/status.states.offline') }}</span>
                                @endif
                            </td>
                            <td class="text-right" style="white-space: nowrap;">
                                @if($canSave)
                                    <form action="{{ route('admin.design.status.view', $service->id) }}" method="POST" style="display: inline;">
                                        {!! csrf_field() !!}
                                        {!! method_field('DELETE') !!}
                                        <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash-o"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">{{ __('admin/status.none') }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        @if($canSave)
            <form action="{{ $editing ? route('admin.design.status.view', $editing->id) : route('admin.design.status') }}" method="POST" autocomplete="off">
                {!! csrf_field() !!}
                @if($editing) {!! method_field('PATCH') !!} @endif
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ $editing ? __('admin/status.edit') : __('admin/status.add') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="statusName" class="control-label">{{ __('admin/status.name') }}</label>
                            <input type="text" id="statusName" name="name" class="form-control" maxlength="64" value="{{ old('name', $editing->name ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label for="statusType" class="control-label">{{ __('admin/status.type') }}</label>
                            <select id="statusType" name="type" class="form-control">
                                @foreach(['http', 'tcp'] as $type)
                                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ __('admin/status.types.' . $type) }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted small no-margin">{{ __('admin/status.type_help') }}</p>
                        </div>
                        <div class="form-group">
                            <label for="statusTarget" class="control-label">{{ __('admin/status.target') }}</label>
                            <input type="text" id="statusTarget" name="target" class="form-control" maxlength="2048" value="{{ old('target', $editing->target ?? '') }}" placeholder="https://example.com">
                            <p class="text-muted small no-margin">{{ __('admin/status.target_help') }}</p>
                        </div>
                        <div class="form-group">
                            <label for="statusSort" class="control-label">{{ __('admin/status.sort') }}</label>
                            <input type="number" min="0" max="9999" id="statusSort" name="sort" class="form-control" value="{{ old('sort', $editing->sort ?? 0) }}">
                        </div>
                        <div class="checkbox checkbox-primary no-margin">
                            <input type="checkbox" id="statusEnabled" name="enabled" value="1" @checked($enabled)>
                            <label for="statusEnabled">{{ __('admin/status.enabled') }}</label>
                        </div>
                    </div>
                    <div class="box-footer">
                        @if($editing)
                            <a href="{{ route('admin.design.status') }}" class="btn btn-sm btn-default">{{ __('admin/common.actions.cancel') }}</a>
                        @endif
                        <button type="submit" class="btn btn-sm btn-success pull-right">{{ __('admin/common.actions.save') }}</button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
