@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.backups.title', ['name' => $node->name]) }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>{{ __('admin/nodes.backups.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('admin/nodes.title') }}</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">{{ __('admin/nodes.tabs.backups') }}</li>
    </ol>
@endsection

@section('content')
@include('partials.admin.nodes.tabs', ['active' => 'backups'])
@unless($addon->enabled())
<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-warning">{!! __('admin/nodes.backups.addon_off', ['url' => route('admin.design.addons')]) !!}</div>
    </div>
</div>
@endunless
<div class="row">
    <div class="col-md-8">
        <form action="{{ route('admin.nodes.view.backups', $node->id) }}" method="POST" autocomplete="off">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/nodes.backups.server') }}</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="form-group col-sm-8">
                            <label for="sftpHost" class="control-label">{{ __('admin/nodes.backups.host') }}</label>
                            <input type="text" id="sftpHost" name="host" class="form-control" value="{{ old('host', $target['host']) }}" required>
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="sftpPort" class="control-label">{{ __('admin/nodes.backups.port') }}</label>
                            <input type="number" id="sftpPort" name="port" min="1" max="65535" class="form-control" value="{{ old('port', $target['port']) }}" required>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="sftpUsername" class="control-label">{{ __('admin/nodes.backups.username') }}</label>
                            <input type="text" id="sftpUsername" name="username" class="form-control" value="{{ old('username', $target['username']) }}" required>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="sftpPassword" class="control-label">{{ __('admin/nodes.backups.password') }}</label>
                            <input type="password" id="sftpPassword" name="password" class="form-control" autocomplete="new-password"
                                   placeholder="{{ $target['has_password'] ? __('admin/nodes.backups.unchanged') : '' }}">
                        </div>
                        <div class="form-group col-xs-12">
                            <label for="sftpKey" class="control-label">{{ __('admin/nodes.backups.private_key') }}</label>
                            <textarea id="sftpKey" name="private_key" rows="4" class="form-control" style="font-family: monospace;"
                                      placeholder="{{ $target['has_private_key'] ? __('admin/nodes.backups.unchanged') : '-----BEGIN OPENSSH PRIVATE KEY-----' }}"></textarea>
                            <p class="text-muted small">{{ __('admin/nodes.backups.private_key_help') }}</p>
                        </div>
                        <div class="form-group col-xs-12">
                            <label for="sftpPath" class="control-label">{{ __('admin/nodes.backups.path') }}</label>
                            <input type="text" id="sftpPath" name="path" class="form-control" value="{{ old('path', $target['path']) }}" placeholder="backups/{{ $node->name }}">
                            <p class="text-muted small">{{ __('admin/nodes.backups.path_help') }}</p>
                        </div>
                        <div class="form-group col-xs-12 no-margin">
                            <div class="checkbox checkbox-primary no-margin">
                                <input type="checkbox" id="sftpEnabled" name="enabled" value="1" @checked(old('enabled', $target['enabled']))>
                                <label for="sftpEnabled">{{ __('admin/nodes.backups.enabled') }}</label>
                            </div>
                            <p class="text-muted small no-margin">{{ __('admin/nodes.backups.enabled_help') }}</p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/nodes.backups.save') }}</button>
                    <p class="text-muted small no-margin" style="padding-top: 5px;">{{ __('admin/nodes.backups.save_help') }}</p>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.backups.status') }}</h3>
            </div>
            <div class="box-body">
                <table class="table table-condensed no-margin">
                    <tr>
                        <th style="width: 45%;">{{ __('admin/nodes.backups.state') }}</th>
                        <td>
                            @if($target['configured'] && $target['enabled'] && $addon->enabled())
                                <span class="label label-success">{{ __('admin/nodes.backups.state_active') }}</span>
                            @elseif($target['configured'])
                                <span class="label label-default">{{ __('admin/nodes.backups.state_inactive') }}</span>
                            @else
                                <span class="label label-default">{{ __('admin/nodes.backups.state_none') }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/nodes.backups.host_key') }}</th>
                        <td><code style="word-break: break-all;">{{ $target['host_key'] ?? '—' }}</code></td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/nodes.backups.stored') }}</th>
                        <td>{{ $stored }}</td>
                    </tr>
                </table>
                <p class="text-muted small" style="margin: 10px 0 0;">{{ __('admin/nodes.backups.host_key_help') }}</p>
            </div>
            @if($target['host'] !== '')
                <div class="box-footer">
                    <form action="{{ route('admin.nodes.view.backups.delete', $node->id) }}" method="POST">
                        {!! csrf_field() !!}
                        {!! method_field('DELETE') !!}
                        <button type="submit" class="btn btn-sm btn-danger" @disabled($stored > 0)>{{ __('admin/nodes.backups.remove') }}</button>
                    </form>
                </div>
            @endif
        </div>
        <div class="callout callout-info">
            <p class="small no-margin">{{ __('admin/nodes.backups.info') }}</p>
        </div>
    </div>
</div>
@endsection
