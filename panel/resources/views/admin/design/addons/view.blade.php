@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'addons'])

@php($canSave = Auth::user()->hasAdminPermission('addons.update'))

@section('title')
    {{ $addon->name() }}
@endsection

@section('content-header')
    <h1>{{ $addon->name() }}<small>{{ __('admin/addons.settings') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li><a href="{{ route('admin.design.addons') }}">{{ __('admin/design.tabs.addons') }}</a></li>
        <li class="active">{{ $addon->name() }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.addons.view', $addon->key()) }}" method="POST" enctype="multipart/form-data">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <div class="hydra-addon-head">
                            <span class="hydra-addon-icon @if($addon->enabled()) is-active @endif"><x-hydra.icon :name="$addon->icon()" class="hydra-addon-icon-svg" /></span>
                            <div class="hydra-addon-title">
                                <strong>{{ $addon->name() }}</strong>
                                <span class="text-muted small">{{ $addon->description() }}</span>
                            </div>
                        </div>
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" id="addonEnabled" name="enabled" value="1" @checked(old('enabled', $addon->enabled())) @disabled(!$canSave)>
                            <label for="addonEnabled">{{ __('admin/addons.enabled_label') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <fieldset @disabled(!$canSave)>
            @include($addon->view(), ['addon' => $addon])
        </fieldset>
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-footer">
                        <a href="{{ route('admin.design.addons') }}" class="btn btn-sm btn-default">{{ __('admin/addons.back') }}</a>
                        @if($canSave)
                            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
