@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'eggs'])

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('title')
    {{ __('admin/design.eggs.title') }} – {{ $egg->name }}
@endsection

@section('content-header')
    <h1>{{ $egg->name }}<small>{{ $egg->nest->name }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li><a href="{{ route('admin.design.eggs') }}">{{ __('admin/design.eggs.title') }}</a></li>
        <li class="active">{{ $egg->name }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <div class="row">
        {{-- Picture in the server list --}}
        <div class="col-md-6">
            <form action="{{ route('admin.design.eggs.image', [$egg->id, 'list']) }}" method="POST" enctype="multipart/form-data">
                {!! csrf_field() !!}
                {!! method_field('PATCH') !!}
                @include('admin.design.eggs.partials.image', [
                    'title' => __('admin/design.eggs.list_title'),
                    'help' => __('admin/design.eggs.list_help'),
                    'format' => __('admin/design.eggs.list_format'),
                    'image' => $images['list'],
                    'resetLabel' => __('admin/design.eggs.reset_list'),
                    'height' => '180px',
                ])
            </form>
        </div>

        {{-- Banner of the server dashboard --}}
        <div class="col-md-6">
            <form action="{{ route('admin.design.eggs.image', [$egg->id, 'banner']) }}" method="POST" enctype="multipart/form-data">
                {!! csrf_field() !!}
                {!! method_field('PATCH') !!}
                @include('admin.design.eggs.partials.image', [
                    'title' => __('admin/design.banner.title'),
                    'help' => __('admin/design.banner.help'),
                    'format' => __('admin/design.banner.format'),
                    'image' => $images['banner'],
                    'resetLabel' => __('admin/design.banner.reset'),
                    'height' => '120px',
                ])
            </form>
        </div>
    </div>

    {{-- Background of the server pages --}}
    <form action="{{ route('admin.design.eggs.background', $egg->id) }}" method="POST" enctype="multipart/form-data">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <div class="row">
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.backgrounds.egg') }}</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">{{ __('admin/design.backgrounds.egg_help') }}</p>
                        @include('partials.admin.hydra.background-editor', [
                            'prefix' => 'bg_egg',
                            'background' => $background ?? ['image' => null, 'blur' => 0, 'dim' => 0],
                            'resetName' => 'reset_background',
                            'resetValue' => '1',
                            'resetLabel' => __('admin/design.backgrounds.egg_reset'),
                        ])
                    </div>
                    @if($canSave)
                        <div class="box-footer">
                            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </form>
@endsection

@section('footer-scripts')
    @parent
    @include('partials.admin.hydra.background-editor-script')
@endsection
