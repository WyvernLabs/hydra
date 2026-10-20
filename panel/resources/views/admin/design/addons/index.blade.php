@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'addons'])

@php($canSave = Auth::user()->hasAdminPermission('addons.update'))

@section('title')
    {{ __('admin/addons.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/addons.title') }}<small>{{ __('admin/addons.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.addons') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <p class="text-muted small">{{ __('admin/addons.help') }}</p>
    <div class="hydra-addon-grid">
        @foreach($addons as $addon)
            <div class="hydra-addon-card @if($addon->enabled()) is-active @endif">
                <a href="{{ route('admin.design.addons.view', $addon->key()) }}" class="hydra-addon-card-link" aria-label="{{ __('admin/addons.settings') }}: {{ $addon->name() }}"></a>
                <div class="hydra-addon-head">
                    <span class="hydra-addon-icon"><x-hydra.icon :name="$addon->icon()" class="hydra-addon-icon-svg" /></span>
                    <div class="hydra-addon-title">
                        <strong>{{ $addon->name() }}</strong>
                        <span class="hydra-addon-status">{{ $addon->enabled() ? __('admin/addons.active') : __('admin/addons.inactive') }}</span>
                    </div>
                    @if($canSave)
                        <form action="{{ route('admin.design.addons.toggle', $addon->key()) }}" method="POST" class="hydra-addon-toggle">
                            {!! csrf_field() !!}
                            <input type="hidden" name="enabled" value="{{ $addon->enabled() ? 0 : 1 }}">
                            <button type="submit" class="hydra-switch @if($addon->enabled()) is-on @endif" role="switch" aria-checked="{{ $addon->enabled() ? 'true' : 'false' }}"
                                    title="{{ $addon->enabled() ? __('admin/addons.disable') : __('admin/addons.enable') }}">
                                <span class="sr-only">{{ $addon->enabled() ? __('admin/addons.disable') : __('admin/addons.enable') }}</span>
                            </button>
                        </form>
                    @endif
                </div>
                <p class="hydra-addon-description">{{ $addon->description() }}</p>
                @if(!empty($addon->summary()))
                    <ul class="hydra-addon-summary">
                        @foreach($addon->summary() as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
@endsection
