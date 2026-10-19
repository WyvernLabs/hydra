@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'links'])

@section('title')
    {{ __('admin/settings.links.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/settings.links.title') }}<small>{{ __('admin/settings.links.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/settings.breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.links.list') }}</h3>
                    @if(Auth::user()->hasAdminPermission('settings.update'))
                        <div class="box-tools">
                            <a href="{{ route('admin.settings.links.new') }}" class="btn btn-sm btn-primary">{{ __('admin/common.actions.create_new') }}</a>
                        </div>
                    @endif
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('admin/settings.links.link_title') }}</th>
                                <th>{{ __('admin/settings.links.type') }}</th>
                                <th>{{ __('admin/settings.links.target') }}</th>
                                <th class="text-center">{{ __('admin/settings.links.sort') }}</th>
                                <th class="text-center">{{ __('admin/settings.links.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($links as $link)
                                <tr>
                                    <td><a href="{{ route('admin.settings.links.view', $link->id) }}">{{ $link->localizedTitle() }}</a></td>
                                    <td>{{ __('admin/settings.links.types.' . $link->type) }}</td>
                                    <td>
                                        @if($link->type === 'page')
                                            <a href="/pages/{{ $link->slug }}" target="_blank"><code>/pages/{{ $link->slug }}</code></a>
                                        @elseif($link->url)
                                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"><code>{{ str_limit($link->url, 50) }}</code></a>
                                        @else
                                            <span class="text-muted">{{ __('admin/settings.links.no_url') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $link->sort }}</td>
                                    <td class="text-center">
                                        @if(!$link->enabled)
                                            <span class="label label-default">{{ __('admin/settings.links.disabled') }}</span>
                                        @elseif($link->type === 'url' && !$link->url)
                                            <span class="label label-warning">{{ __('admin/settings.links.hidden') }}</span>
                                        @else
                                            <span class="label label-success">{{ __('admin/settings.links.visible') }}</span>
                                        @endif
                                        @if($link->public)
                                            <span class="label label-primary">{{ __('admin/settings.links.public_badge') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">{{ __('admin/settings.links.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
