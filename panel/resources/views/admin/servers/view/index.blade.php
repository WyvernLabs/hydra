@extends('layouts.admin')

@section('title')
    {{ __('admin/servers.page_title', ['name' => $server->name]) }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ str_limit($server->description) }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li class="active">{{ $server->name }}</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-sm-8">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/servers.view.information') }}</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <tr>
                                <td>{{ __('admin/servers.view.internal_id') }}</td>
                                <td><code>{{ $server->id }}</code></td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.external_id') }}</td>
                                @if(is_null($server->external_id))
                                    <td><span class="label label-default">{{ __('admin/servers.not_set') }}</span></td>
                                @else
                                    <td><code>{{ $server->external_id }}</code></td>
                                @endif
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.uuid') }}</td>
                                <td><code>{{ $server->uuid }}</code></td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.egg') }}</td>
                                <td>
                                    <a href="{{ route('admin.nests.view', $server->nest_id) }}">{{ $server->nest->name }}</a> ::
                                    <a href="{{ route('admin.nests.egg.view', $server->egg_id) }}">{{ $server->egg->name }}</a>
                                </td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.server_name') }}</td>
                                <td>{{ $server->name }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.cpu') }}</td>
                                <td>
                                    @if($server->cpu === 0)
                                        <code>{{ __('admin/common.unlimited') }}</code>
                                    @else
                                        <code>{{ $server->cpu }}%</code>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.threads') }}</td>
                                <td>
                                    @if($server->threads != null)
                                        <code>{{ $server->threads }}</code>
                                    @else
                                        <span class="label label-default">{{ __('admin/servers.not_set') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.memory') }}</td>
                                <td>
                                    @if($server->memory === 0)
                                        <code>{{ __('admin/common.unlimited') }}</code>
                                    @else
                                        <code>{{ $server->memory }}MiB</code>
                                    @endif
                                    /
                                    @if($server->swap === 0)
                                        <code data-toggle="tooltip" data-placement="top" title="{{ __('admin/servers.view.swap') }}">{{ __('admin/servers.not_set') }}</code>
                                    @elseif($server->swap === -1)
                                        <code data-toggle="tooltip" data-placement="top" title="{{ __('admin/servers.view.swap') }}">{{ __('admin/common.unlimited') }}</code>
                                    @else
                                        <code data-toggle="tooltip" data-placement="top" title="{{ __('admin/servers.view.swap') }}"> {{ $server->swap }}MiB</code>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.disk') }}</td>
                                <td>
                                    @if($server->disk === 0)
                                        <code>{{ __('admin/common.unlimited') }}</code>
                                    @else
                                        <code>{{ $server->disk }}MiB</code>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.io') }}</td>
                                <td><code>{{ $server->io }}</code></td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.default_connection') }}</td>
                                <td><code>{{ $server->allocation->ip }}:{{ $server->allocation->port }}</code></td>
                            </tr>
                            <tr>
                                <td>{{ __('admin/servers.view.alias') }}</td>
                                <td>
                                    @if($server->allocation->alias !== $server->allocation->ip)
                                        <code>{{ $server->allocation->alias }}:{{ $server->allocation->port }}</code>
                                    @else
                                        <span class="label label-default">{{ __('admin/servers.view.no_alias') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-primary">
            <div class="box-body" style="padding-bottom: 0px;">
                <div class="row">
                    @if($server->isSuspended())
                        <div class="col-sm-12">
                            <div class="small-box bg-yellow">
                                <div class="inner">
                                    <h3 class="no-margin">{{ __('admin/servers.suspended') }}</h3>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if(!$server->isInstalled())
                        <div class="col-sm-12">
                            <div class="small-box {{ (! $server->isInstalled()) ? 'bg-blue' : 'bg-maroon' }}">
                                <div class="inner">
                                    <h3 class="no-margin">{{ (! $server->isInstalled()) ? __('admin/servers.installing') : __('admin/servers.install_failed') }}</h3>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="col-sm-12">
                        <div class="small-box bg-gray">
                            <div class="inner">
                                <h3>{{ str_limit($server->user->username, 16) }}</h3>
                                <p>{{ __('admin/servers.view.owner') }}</p>
                            </div>
                            <div class="icon"><i class="fa fa-user"></i></div>
                            <a href="{{ route('admin.users.view', $server->user->id) }}" class="small-box-footer">
                                {{ __('admin/servers.view.more_info') }} <i class="fa fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <div class="small-box bg-gray">
                            <div class="inner">
                                <h3>{{ str_limit($server->node->name, 16) }}</h3>
                                <p>{{ __('admin/servers.view.node') }}</p>
                            </div>
                            <div class="icon"><i class="fa fa-codepen"></i></div>
                            <a href="{{ route('admin.nodes.view', $server->node->id) }}" class="small-box-footer">
                                {{ __('admin/servers.view.more_info') }} <i class="fa fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
