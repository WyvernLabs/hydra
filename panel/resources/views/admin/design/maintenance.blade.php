@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'maintenance'])

@section('designer-preview', json_encode(collect($languages)
    ->map(fn ($language, $code) => ['key' => $code, 'label' => strtoupper($code), 'url' => route('admin.design.maintenance.preview', ['lang' => $code])])
    ->values()))

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('title')
    {{ __('admin/design.maintenance.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.maintenance.title') }}<small>{{ __('admin/design.maintenance.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.maintenance') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.maintenance') }}" method="POST" enctype="multipart/form-data">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
            <div class="row">
                <div class="col-md-6">
                  <fieldset @disabled(!$canSave)>
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/design.maintenance.texts') }}</h3>
                        </div>
                        <div class="box-body">
                            @foreach($languages as $code => $language)
                                <div class="form-group">
                                    <label class="control-label" for="maintenanceTitle_{{ $code }}">{{ __('admin/design.maintenance.heading') }} ({{ $language }})</label>
                                    <input type="text" id="maintenanceTitle_{{ $code }}" name="title[{{ $code }}]" class="form-control"
                                           maxlength="{{ \Pterodactyl\Services\Hydra\MaintenancePageService::MAX_TITLE }}"
                                           value="{{ old("title.$code", $values['title'][$code] ?? '') }}" placeholder="{{ $defaults[$code]['title'] }}">
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="maintenanceDescription_{{ $code }}">{{ __('admin/design.maintenance.description') }} ({{ $language }})</label>
                                    <textarea id="maintenanceDescription_{{ $code }}" name="description[{{ $code }}]" class="form-control" rows="3"
                                              maxlength="{{ \Pterodactyl\Services\Hydra\MaintenancePageService::MAX_DESCRIPTION }}"
                                              placeholder="{{ $defaults[$code]['description'] }}">{{ old("description.$code", $values['description'][$code] ?? '') }}</textarea>
                                </div>
                            @endforeach
                            <p class="text-muted small">{{ __('admin/design.maintenance.texts_help') }}</p>
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="maintenanceLogo" name="logo" type="checkbox" value="1" @if(old('logo', $values['logo'])) checked @endif>
                                <label for="maintenanceLogo">{{ __('admin/design.maintenance.logo') }}</label>
                            </div>
                            <p class="text-muted no-margin"><small>{{ __('admin/design.maintenance.logo_help') }}</small></p>
                        </div>
                    </div>
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/design.maintenance.background') }}</h3>
                        </div>
                        <div class="box-body">
                            <p class="text-muted small">{{ __('admin/design.maintenance.background_help') }}</p>
                            @include('partials.admin.hydra.background-editor', [
                                'prefix' => 'bg',
                                'background' => $background,
                                'resetName' => 'reset_background',
                                'resetValue' => '1',
                            ])
                        </div>
                    </div>
                  </fieldset>
                </div>
            </div>
            @if($canSave)
                <div class="row">
                    <div class="col-xs-12">
                        <div class="box">
                            <div class="box-footer">
                                <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
    </form>
@endsection

@section('footer-scripts')
    @parent
    @include('partials.admin.hydra.background-editor-script')
@endsection
