@extends('layouts.admin')

@section('title')
    {{ __('admin/servers.page_title_tab', ['name' => $server->name, 'tab' => __('admin/servers.tabs.details')]) }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ __('admin/servers.details.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">{{ __('admin/servers.tabs.details') }}</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/servers.details.base') }}</h3>
            </div>
            <form action="{{ route('admin.servers.view.details', $server->id) }}" method="POST">
                <div class="box-body">
                    <div class="form-group">
                        <label for="name" class="control-label">{{ __('admin/servers.server_name') }} <span class="field-required"></span></label>
                        <input type="text" name="name" value="{{ old('name', $server->name) }}" class="form-control" />
                        <p class="text-muted small">{!! __('admin/servers.details.name_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="external_id" class="control-label">{{ __('admin/servers.view.external_id') }}</label>
                        <input type="text" name="external_id" value="{{ old('external_id', $server->external_id) }}" class="form-control" />
                        <p class="text-muted small">{{ __('admin/servers.details.external_id_help') }}</p>
                    </div>
                    <div class="form-group">
                        <label for="pUserId" class="control-label">{{ __('admin/servers.fields.owner') }} <span class="field-required"></span></label>
                        <select name="owner_id" class="form-control" id="pUserId">
                            <option value="{{ $server->owner_id }}" selected>{{ $server->user->email }}</option>
                        </select>
                        <p class="text-muted small">{{ __('admin/servers.details.owner_help') }}</p>
                    </div>
                    <div class="form-group">
                        <label for="description" class="control-label">{{ __('admin/servers.fields.description') }}</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $server->description) }}</textarea>
                        <p class="text-muted small">{{ __('admin/servers.fields.description_help') }}</p>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <input type="submit" class="btn btn-sm btn-primary" value="{{ __('admin/servers.details.update') }}" />
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    $('#pUserId').select2({
        ajax: {
            url: '/admin/users/accounts.json',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    filter: { username: params.term },
                    page: params.page,
                };
            },
            processResults: function (data, params) {
                return { results: data };
            },
            cache: true,
        },
        escapeMarkup: function (markup) { return markup; },
        minimumInputLength: 2,
        templateResult: function (data) {
            if (data.loading) return escapeHtml(data.text);

            return '<div class="user-block"> \
                <span class="username"> \
                    <a href="#">' + escapeHtml(data.name_first) + ' ' + escapeHtml(data.name_last) +'</a> \
                </span> \
                <span class="description"><strong>' + escapeHtml(data.username) + '</strong> - ' + escapeHtml(data.email) + '</span> \
            </div>';
        },
        templateSelection: function (data) {
            if (typeof data.name_first === 'undefined') {
                data = {
                    md5: '{{ md5(strtolower($server->user->email)) }}',
                    name_first: '{{ $server->user->name_first }}',
                    name_last: '{{ $server->user->name_last }}',
                    email: '{{ $server->user->email }}',
                    username: '{{ $server->user->username }}',
                    id: {{ $server->owner_id }}
                };
            }

            return '<div> \
                <span> \
                </span> \
                <span style="padding-left:5px;"> \
                    ' + escapeHtml(data.name_first) + ' ' + escapeHtml(data.name_last) + ' (<strong>' + escapeHtml(data.username) + '</strong>) \
                </span> \
            </div>';
        }
    });
    </script>
@endsection
