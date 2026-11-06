@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'announcements'])

@section('title')
    {{ __('admin/announcements.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/announcements.title') }}<small>{{ __('admin/announcements.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/announcements.title') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/announcements.list') }}</h3>
                    @if(Auth::user()->hasAdminPermission('announcements.update'))
                        <div class="box-tools">
                            <a href="{{ route('admin.design.announcements.new') }}" class="btn btn-sm btn-primary">{{ __('admin/common.actions.create_new') }}</a>
                        </div>
                    @endif
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('admin/announcements.heading') }}</th>
                                <th>{{ __('admin/announcements.type') }}</th>
                                <th>{{ __('admin/announcements.target') }}</th>
                                <th>{{ __('admin/announcements.expires_at') }}</th>
                                <th class="text-center">{{ __('admin/announcements.sort') }}</th>
                                <th class="text-center">{{ __('admin/announcements.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($announcements as $item)
                                <tr>
                                    <td><a href="{{ route('admin.design.announcements.view', $item->id) }}">{{ $item->localized($item->title) }}</a></td>
                                    <td>{{ __('admin/announcements.types.' . $item->type) }}</td>
                                    <td>
                                        @if($item->isGeneral())
                                            {{ __('admin/announcements.general') }}
                                        @else
                                            {{ collect($item->nodes)->map(fn ($id) => $nodes[$id] ?? null)->filter()->implode(', ') ?: __('admin/announcements.no_nodes') }}
                                        @endif
                                    </td>
                                    <td>{{ $item->expires_at?->format(__('admin/announcements.date_format')) ?? __('admin/announcements.no_expiry') }}</td>
                                    <td class="text-center">{{ $item->sort }}</td>
                                    <td class="text-center">
                                        @if($item->enabled && $item->isExpired())
                                            <span class="label label-warning">{{ __('admin/announcements.expired') }}</span>
                                        @else
                                            <span class="label {{ $item->enabled ? 'label-success' : 'label-default' }}">{{ $item->enabled ? __('admin/common.enabled') : __('admin/common.disabled') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">{{ __('admin/announcements.none') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
