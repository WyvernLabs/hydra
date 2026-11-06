@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'effects'])

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('title')
    {{ __('admin/design.effects.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.effects.title') }}<small>{{ __('admin/design.effects.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.effects') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.effects') }}" method="POST">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.effects.event') }}</h3>
                    </div>
                    <div class="box-body">
                        @foreach($events as $event)
                            <div class="radio radio-primary">
                                <input type="radio" id="effect-{{ $event }}" name="event" value="{{ $event }}" @checked($current === $event) @disabled(!$canSave)>
                                <label for="effect-{{ $event }}"><strong>{{ __('admin/design.effects.events.' . $event . '.name') }}</strong></label>
                                <p class="text-muted small no-margin">{{ __('admin/design.effects.events.' . $event . '.description') }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if($canSave)
                        <div class="box-footer">
                            <button type="submit" class="btn btn-success pull-right">{{ __('admin/common.actions.save') }}</button>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="box">
                    <div class="box-body">
                        <p class="no-margin">{{ __('admin/design.effects.users_hint') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
