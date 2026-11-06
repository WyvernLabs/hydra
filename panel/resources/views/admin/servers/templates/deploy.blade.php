@extends('layouts.admin')

@php($canManage = Auth::user()->hasAdminPermission('servers.templates'))

@section('title')
    {{ __('admin/server_templates.deploy.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/server_templates.deploy.title') }}<small>{{ __('admin/server_templates.deploy.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li class="active">{{ __('admin/server_templates.deploy.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        @if($templates->isEmpty())
            <div class="box">
                <div class="box-body text-center">
                    <p class="text-muted">{{ __('admin/server_templates.none') }}</p>
                    @if($canManage)
                        <a href="{{ route('admin.servers.templates.new') }}" class="btn btn-sm btn-primary">{{ __('admin/server_templates.new') }}</a>
                    @endif
                </div>
            </div>
        @else
            <form action="{{ route('admin.servers.new.template') }}" method="POST">
                {!! csrf_field() !!}
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/servers.new.core') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="pName">{{ __('admin/servers.server_name') }}</label>
                            <input type="text" id="pName" name="name" class="form-control" maxlength="191" value="{{ old('name') }}" placeholder="{{ __('admin/servers.server_name') }}">
                        </div>
                        <div class="form-group">
                            <label for="pUserId">{{ __('admin/servers.fields.owner') }}</label>
                            <select id="pUserId" name="owner_id" class="form-control" style="padding-left:0;"></select>
                            <p class="small text-muted no-margin">{{ __('admin/servers.fields.owner_help') }}</p>
                        </div>
                        <div class="form-group no-margin-bottom">
                            <label for="pTemplate">{{ __('admin/server_templates.deploy.template') }}</label>
                            <select id="pTemplate" name="template_id" class="form-control">
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" @selected($selected === $template->id)>{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-success pull-right">{{ __('admin/servers.new.create') }}</button>
                    </div>
                </div>
            </form>
        @endif
    </div>
    @if($templates->isNotEmpty())
        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/server_templates.deploy.summary') }}</h3>
                    @if($canManage)
                        <div class="box-tools"><a href="{{ route('admin.servers.templates') }}" class="btn btn-xs btn-default">{{ __('admin/addons.server_templates.manage') }}</a></div>
                    @endif
                </div>
                @foreach($templates as $template)
                    <div class="box-body table-responsive no-padding hydra-template-summary" data-template="{{ $template->id }}" style="display:none;">
                        @if($template->description)<p class="small text-muted" style="margin: 10px;">{{ $template->description }}</p>@endif
                        <table class="table">
                            <tr><td>{{ __('admin/servers.fields.egg') }}</td><td>{{ $template->egg->nest->name }} / {{ $template->egg->name }}</td></tr>
                            <tr><td>{{ __('admin/servers.fields.cpu') }}</td><td>{{ $template->cpu ? $template->cpu . ' %' : __('admin/server_templates.unlimited') }}</td></tr>
                            <tr><td>{{ __('admin/servers.fields.memory') }}</td><td>{{ $template->memory ? $template->memory . ' MiB' : __('admin/server_templates.unlimited') }}</td></tr>
                            <tr><td>{{ __('admin/servers.fields.disk') }}</td><td>{{ $template->disk ? $template->disk . ' MiB' : __('admin/server_templates.unlimited') }}</td></tr>
                            <tr><td>{{ __('admin/servers.fields.database_limit') }}</td><td>{{ $template->database_limit }}</td></tr>
                            <tr><td>{{ __('admin/servers.fields.allocation_limit') }}</td><td>{{ $template->allocation_limit }}</td></tr>
                            <tr><td>{{ __('admin/servers.fields.backup_storage') }}</td><td>{{ round($template->backup_storage_limit / 1024, 2) }} GB</td></tr>
                            <tr><td>{{ __('admin/server_templates.target') }}</td><td>{{ implode(', ', $template->targetNames()) ?: __('admin/server_templates.all_public') }}</td></tr>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function () {
            function escapeHtml(str) {
                return $('<div>').text(str === undefined || str === null ? '' : str).html();
            }

            function initOwner(data) {
                $('#pUserId').select2({
                    ajax: {
                        url: '/admin/users/accounts.json',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { filter: { username: params.term }, page: params.page };
                        },
                        processResults: function (results) {
                            return { results: results };
                        },
                        cache: true,
                    },
                    data: data,
                    escapeMarkup: function (markup) { return markup; },
                    minimumInputLength: 2,
                    templateResult: function (item) {
                        if (item.loading) return escapeHtml(item.text);

                        return '<div class="user-block"><span class="username"><a href="#">' + escapeHtml(item.name_first) + ' ' + escapeHtml(item.name_last)
                            + '</a></span><span class="description"><strong>' + escapeHtml(item.username) + '</strong> - ' + escapeHtml(item.email) + '</span></div>';
                    },
                    templateSelection: function (item) {
                        if (!item.username) return escapeHtml(item.text);

                        return '<span style="padding-left:5px;">' + escapeHtml(item.name_first) + ' ' + escapeHtml(item.name_last) + ' (<strong>' + escapeHtml(item.username) + '</strong>)</span>';
                    },
                });
            }

            @if(old('owner_id'))
                $.ajax({ url: '/admin/users/accounts.json?user_id={{ (int) old('owner_id') }}', dataType: 'json' }).then(function (data) {
                    initOwner([data]);
                });
            @else
                initOwner();
            @endif

            function showSummary() {
                $('.hydra-template-summary').hide().filter('[data-template="' + $('#pTemplate').val() + '"]').show();
            }

            $('#pTemplate').select2({ width: '100%' }).on('change', showSummary);
            showSummary();
        });
    </script>
@endsection
