@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'addons'])

@section('title')
    {{ __('admin/domains.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/domains.title') }}<small>{{ __('admin/domains.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design.addons') }}">{{ __('admin/addons.title') }}</a></li>
        <li><a href="{{ route('admin.design.addons.view', 'subdomains') }}">{{ __('admin/addons.subdomains.name') }}</a></li>
        <li class="active">{{ __('admin/domains.title') }}</li>
    </ol>
@endsection

@section('content')
@yield('design::nav')
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/domains.list') }}</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>{{ __('admin/domains.name') }}</th>
                        <th class="text-center">{{ __('admin/domains.subdomains') }}</th>
                        <th class="text-center">{{ __('admin/domains.state') }}</th>
                        <th></th>
                    </tr>
                    @forelse($domains as $domain)
                        <tr>
                            <td><code>{{ $domain->name }}</code></td>
                            <td class="text-center">{{ $domain->subdomains_count }}</td>
                            <td class="text-center">
                                <span class="label {{ $domain->enabled ? 'label-success' : 'label-default' }}">{{ $domain->enabled ? __('admin/common.enabled') : __('admin/common.disabled') }}</span>
                            </td>
                            <td class="text-right" style="white-space: nowrap;">
                                @if(Auth::user()->hasAdminPermission('addons.update'))
                                    <form action="{{ route('admin.design.domains.update', $domain->id) }}" method="POST" style="display: inline;">
                                        {!! csrf_field() !!}
                                        {!! method_field('PATCH') !!}
                                        <input type="hidden" name="enabled" value="{{ $domain->enabled ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-xs btn-default">{{ $domain->enabled ? __('admin/domains.disable') : __('admin/domains.enable') }}</button>
                                    </form>
                                    <form action="{{ route('admin.design.domains.delete', $domain->id) }}" method="POST" style="display: inline;">
                                        {!! csrf_field() !!}
                                        {!! method_field('DELETE') !!}
                                        <button type="submit" class="btn btn-xs btn-danger" @disabled($domain->subdomains_count > 0) title="{{ __('admin/domains.delete_hint') }}"><i class="fa fa-trash-o"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">{{ __('admin/domains.none') }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        @if(Auth::user()->hasAdminPermission('addons.update'))
            <form action="{{ route('admin.design.domains') }}" method="POST" autocomplete="off">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/domains.add') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="domainName" class="control-label">{{ __('admin/domains.name') }}</label>
                            <input type="text" id="domainName" name="name" class="form-control" placeholder="example.com" value="{{ old('name') }}" required>
                        </div>
                        <div class="form-group no-margin">
                            <label for="domainToken" class="control-label">{{ __('admin/domains.token') }}</label>
                            <input type="password" id="domainToken" name="api_token" class="form-control" autocomplete="new-password" required>
                            <p class="text-muted small no-margin">{{ __('admin/domains.token_help') }}</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/domains.add_button') }}</button>
                    </div>
                </div>
            </form>
        @endif
        <div class="callout callout-info">
            <p class="small no-margin">{{ __('admin/domains.info') }}</p>
        </div>
    </div>
</div>
@endsection
