@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'navigation'])

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('title')
    {{ __('admin/design.navigation.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.navigation.title') }}<small>{{ __('admin/design.navigation.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.navigation') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <p class="text-muted small">{{ __('admin/design.navigation.help') }}</p>
    <form action="{{ route('admin.design.navigation') }}" method="POST" id="hydraNavForm">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <input type="hidden" name="navigation" id="hydraNavInput">
        <div id="hydraNavEditor" class="hydra-nav-editor" data-readonly="{{ $canSave ? '0' : '1' }}"></div>
        <div class="box">
            <div class="box-footer">
                @if($canSave)
                    <button type="button" class="btn btn-sm btn-default" id="hydraNavAdd">{{ __('admin/design.navigation.add_category') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    <button type="submit" name="reset" value="1" class="btn btn-sm btn-link pull-right" id="hydraNavReset">{{ __('admin/design.navigation.reset') }}</button>
                @endif
            </div>
        </div>
    </form>
@endsection

@section('footer-scripts')
    @parent
    @include('partials.admin.hydra.icon-picker-script')
    <script>
        window.HydraNavigation = {
            navigation: @json($navigation),
            buttons: @json($buttons),
            eggs: @json($eggs),
            languages: @json($languages),
            accent: @json($branding->accent()),
            lang: @json(__('admin/design.navigation')),
        };
    </script>
    <script src="/hydra/navigation-editor.js?v={{ substr(md5_file(public_path('hydra/navigation-editor.js')), 0, 8) }}"></script>
@endsection
