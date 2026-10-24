@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'eggs'])

@section('title')
    {{ __('admin/design.eggs.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.eggs.title') }}<small>{{ __('admin/design.eggs.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.eggs.title') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <div class="row">
        <div class="col-xs-12">
            <p class="text-muted small">{{ __('admin/design.eggs.help') }}</p>
        </div>
        @forelse($nests as $nest)
            @continue($nest->eggs->isEmpty())
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ $nest->name }}</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('admin/design.eggs.egg') }}</th>
                                    <th>{{ __('admin/design.eggs.list') }}</th>
                                    <th>{{ __('admin/design.eggs.banner') }}</th>
                                    <th>{{ __('admin/design.eggs.background') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($nest->eggs as $egg)
                                    @php
                                        $list = $backgrounds->imageForEgg($egg, 'list');
                                        $banner = $backgrounds->imageForEgg($egg, 'banner');
                                        $background = $backgrounds->forEgg($egg);
                                    @endphp
                                    <tr class="align-middle">
                                        <td><a href="{{ route('admin.design.eggs.view', $egg->id) }}"><strong>{{ $egg->name }}</strong></a></td>
                                        <td>
                                            @if($list)
                                                <span class="hydra-bg-thumb" style="background-image: url('{{ $list }}')"></span>
                                            @else
                                                <span class="text-muted">{{ __('admin/design.eggs.none') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($banner)
                                                <span class="hydra-bg-thumb" style="background-image: url('{{ $banner }}')"></span>
                                            @else
                                                <span class="text-muted">{{ __('admin/design.eggs.none') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(is_null($background))
                                                <span class="text-muted">{{ __('admin/design.backgrounds.egg_inherits') }}</span>
                                            @else
                                                <span class="hydra-bg-thumb" style="{{ $background['image'] ? "background-image: url('" . $background['image'] . "')" : '' }}"></span>
                                                <span class="text-muted small">{{ $background['blur'] }} px &middot; {{ $background['dim'] }} %</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('admin.design.eggs.view', $egg->id) }}" class="btn btn-xs btn-default">{{ __('admin/design.eggs.edit') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-xs-12">
                <p class="text-muted">{{ __('admin/design.eggs.empty') }}</p>
            </div>
        @endforelse
    </div>
@endsection
