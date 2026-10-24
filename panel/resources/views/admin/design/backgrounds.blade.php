@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'backgrounds'])

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('title')
    {{ __('admin/design.tabs.backgrounds') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.tabs.backgrounds') }}<small>{{ __('admin/design.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.backgrounds') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.backgrounds') }}" method="POST" enctype="multipart/form-data">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <div class="row">
            @foreach($backgrounds as $slot => $background)
                <div class="col-md-6">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __("admin/design.backgrounds.$slot") }}</h3>
                        </div>
                        <div class="box-body">
                            <p class="text-muted small">{{ __("admin/design.backgrounds.{$slot}_help") }}</p>
                            @include('partials.admin.hydra.background-editor', [
                                'prefix' => "bg_$slot",
                                'background' => $background,
                                'resetName' => 'reset_background',
                                'resetValue' => $slot,
                            ])
                        </div>
                    </div>
                </div>
            @endforeach
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
    <div class="row">
        <div class="col-xs-12">
            <p class="text-muted small">{!! __('admin/design.backgrounds.eggs_hint', ['url' => route('admin.design.eggs')]) !!}</p>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    @include('partials.admin.hydra.background-editor-script')
@endsection
