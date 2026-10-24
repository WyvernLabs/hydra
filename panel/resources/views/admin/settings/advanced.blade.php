@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'advanced'])

@section('title')
    {{ __('admin/settings.advanced.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/settings.advanced.title') }}<small>{{ __('admin/settings.advanced.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/settings.breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <div class="row">
        <div class="col-xs-12">
            <form action="" method="POST">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/settings.advanced.http') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label class="control-label">{{ __('admin/settings.advanced.connect_timeout') }}</label>
                                <div>
                                    <input type="number" required class="form-control" name="pterodactyl:guzzle:connect_timeout" value="{{ old('pterodactyl:guzzle:connect_timeout', config('pterodactyl.guzzle.connect_timeout')) }}">
                                    <p class="text-muted small">{{ __('admin/settings.advanced.connect_timeout_help') }}</p>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="control-label">{{ __('admin/settings.advanced.request_timeout') }}</label>
                                <div>
                                    <input type="number" required class="form-control" name="pterodactyl:guzzle:timeout" value="{{ old('pterodactyl:guzzle:timeout', config('pterodactyl.guzzle.timeout')) }}">
                                    <p class="text-muted small">{{ __('admin/settings.advanced.request_timeout_help') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/settings.advanced.allocations') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.advanced.status') }}</label>
                                <div>
                                    <select class="form-control" name="pterodactyl:client_features:allocations:enabled">
                                        <option value="false">{{ __('admin/common.disabled') }}</option>
                                        <option value="true" @if(old('pterodactyl:client_features:allocations:enabled', config('pterodactyl.client_features.allocations.enabled'))) selected @endif>{{ __('admin/common.enabled') }}</option>
                                    </select>
                                    <p class="text-muted small">{{ __('admin/settings.advanced.allocations_help') }}</p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.advanced.range_start') }}</label>
                                <div>
                                    <input type="number" class="form-control" name="pterodactyl:client_features:allocations:range_start" value="{{ old('pterodactyl:client_features:allocations:range_start', config('pterodactyl.client_features.allocations.range_start')) }}">
                                    <p class="text-muted small">{{ __('admin/settings.advanced.range_start_help') }}</p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.advanced.range_end') }}</label>
                                <div>
                                    <input type="number" class="form-control" name="pterodactyl:client_features:allocations:range_end" value="{{ old('pterodactyl:client_features:allocations:range_end', config('pterodactyl.client_features.allocations.range_end')) }}">
                                    <p class="text-muted small">{{ __('admin/settings.advanced.range_end_help') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/settings.advanced.schedules') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.advanced.task_limit') }}</label>
                                <div>
                                    <input type="number" min="1" max="1000" class="form-control" name="pterodactyl:client_features:schedules:per_schedule_task_limit" value="{{ old('pterodactyl:client_features:schedules:per_schedule_task_limit', config('pterodactyl.client_features.schedules.per_schedule_task_limit')) }}">
                                    <p class="text-muted small">{{ __('admin/settings.advanced.task_limit_help') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-footer">
                        {{ csrf_field() }}
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
