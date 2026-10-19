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
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/design.backgrounds.eggs') }}</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted small no-margin">{{ __('admin/design.backgrounds.eggs_help') }}</p>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('admin/design.backgrounds.egg_name') }}</th>
                                <th>{{ __('admin/design.backgrounds.nest') }}</th>
                                <th>{{ __('admin/design.backgrounds.egg_status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($eggs as ['egg' => $egg, 'background' => $background])
                                <tr>
                                    <td>{{ $egg->name }}</td>
                                    <td>{{ $egg->nest->name }}</td>
                                    <td>
                                        @if(is_null($background))
                                            <span class="text-muted">{{ __('admin/design.backgrounds.egg_inherits') }}</span>
                                        @else
                                            <span class="hydra-bg-thumb" style="{{ $background['image'] ? "background-image: url('" . $background['image'] . "')" : '' }}"></span>
                                            {{ $background['image'] ? __('admin/design.backgrounds.custom') : __('admin/design.backgrounds.default') }}
                                            <span class="text-muted small">&middot; {{ $background['blur'] }} px &middot; {{ $background['dim'] }} %</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if(Auth::user()->hasAdminPermission('eggs.view'))
                                            <a href="{{ route('admin.nests.egg.view', $egg->id) }}#egg-background" class="btn btn-xs btn-default">{{ __('admin/design.backgrounds.egg_edit') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    @include('partials.admin.hydra.background-editor-script')
@endsection
