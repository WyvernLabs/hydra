@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'links'])

@section('title')
    {{ __('admin/design.links.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.links.title') }}<small>{{ __('admin/design.links.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.links') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/design.links.list') }}</h3>
                    @if(Auth::user()->hasAdminPermission('design.update'))
                        <div class="box-tools">
                            <a href="{{ route('admin.design.links.new') }}" class="btn btn-sm btn-primary">{{ __('admin/common.actions.create_new') }}</a>
                        </div>
                    @endif
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('admin/design.links.link_title') }}</th>
                                <th>{{ __('admin/design.links.type') }}</th>
                                <th>{{ __('admin/design.links.target') }}</th>
                                <th class="text-center">{{ __('admin/design.links.sort') }}</th>
                                <th class="text-center">{{ __('admin/design.links.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($links as $link)
                                <tr>
                                    <td><a href="{{ route('admin.design.links.view', $link->id) }}">{{ $link->localizedTitle() }}</a></td>
                                    <td>{{ __('admin/design.links.types.' . $link->type) }}</td>
                                    <td>
                                        @if($link->type === 'page')
                                            <a href="/pages/{{ $link->slug }}" target="_blank"><code>/pages/{{ $link->slug }}</code></a>
                                        @elseif($link->url)
                                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"><code>{{ str_limit($link->url, 50) }}</code></a>
                                        @else
                                            <span class="text-muted">{{ __('admin/design.links.no_url') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $link->sort }}</td>
                                    <td class="text-center">
                                        @if(!$link->enabled)
                                            <span class="label label-default">{{ __('admin/design.links.disabled') }}</span>
                                        @elseif($link->type === 'url' && !$link->url)
                                            <span class="label label-warning">{{ __('admin/design.links.hidden') }}</span>
                                        @else
                                            <span class="label label-success">{{ __('admin/design.links.visible') }}</span>
                                        @endif
                                        @if($link->public)
                                            <span class="label label-primary">{{ __('admin/design.links.public_badge') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">{{ __('admin/design.links.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
