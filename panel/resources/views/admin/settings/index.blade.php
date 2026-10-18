@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'basic'])

@section('title')
    {{ __('admin/settings.general.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/settings.general.title') }}<small>{{ __('admin/settings.general.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/settings.breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.general.title') }}</h3>
                </div>
                <form action="{{ route('admin.settings') }}" method="POST">
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.general.company_name') }}</label>
                                <div>
                                    <input type="text" class="form-control" name="app:name" value="{{ old('app:name', config('app.name')) }}" />
                                    <p class="text-muted"><small>{{ __('admin/settings.general.company_name_help') }}</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.general.two_factor') }}</label>
                                <div>
                                    <div class="btn-group" data-toggle="buttons">
                                        @php
                                            $level = old('pterodactyl:auth:2fa_required', config('pterodactyl.auth.2fa_required'));
                                        @endphp
                                        <label class="btn btn-primary @if ($level == 0) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="0" @if ($level == 0) checked @endif> {{ __('admin/settings.general.two_factor_none') }}
                                        </label>
                                        <label class="btn btn-primary @if ($level == 1) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="1" @if ($level == 1) checked @endif> {{ __('admin/settings.general.two_factor_admins') }}
                                        </label>
                                        <label class="btn btn-primary @if ($level == 2) active @endif">
                                            <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="2" @if ($level == 2) checked @endif> {{ __('admin/settings.general.two_factor_all') }}
                                        </label>
                                    </div>
                                    <p class="text-muted"><small>{{ __('admin/settings.general.two_factor_help') }}</small></p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.general.language') }}</label>
                                <div>
                                    <select name="app:locale" class="form-control">
                                        @foreach($languages as $key => $value)
                                            <option value="{{ $key }}" @if(config('app.locale') === $key) selected @endif>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-muted"><small>{{ __('admin/settings.general.language_help') }}</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
