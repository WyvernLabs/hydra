@extends('layouts.admin')

@section('title')
    {{ __('admin/databases.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/databases.title') }}<small>{{ __('admin/databases.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/databases.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/databases.list') }}</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newHostModal">{{ __('admin/common.actions.create_new') }}</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>{{ __('admin/databases.id') }}</th>
                            <th>{{ __('admin/databases.name') }}</th>
                            <th>{{ __('admin/databases.host') }}</th>
                            <th>{{ __('admin/databases.port') }}</th>
                            <th>{{ __('admin/databases.username') }}</th>
                            <th class="text-center">{{ __('admin/databases.databases') }}</th>
                            <th class="text-center">{{ __('admin/databases.node') }}</th>
                        </tr>
                        @foreach ($hosts as $host)
                            <tr>
                                <td><code>{{ $host->id }}</code></td>
                                <td><a href="{{ route('admin.databases.view', $host->id) }}">{{ $host->name }}</a></td>
                                <td><code>{{ $host->host }}</code></td>
                                <td><code>{{ $host->port }}</code></td>
                                <td>{{ $host->username }}</td>
                                <td class="text-center">{{ $host->databases_count }}</td>
                                <td class="text-center">
                                    @if(! is_null($host->node))
                                        <a href="{{ route('admin.nodes.view', $host->node->id) }}">{{ $host->node->name }}</a>
                                    @else
                                        <span class="label label-default">{{ __('admin/common.none') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="newHostModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.databases') }}" method="POST">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('admin/common.actions.cancel') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">{{ __('admin/databases.create_title') }}</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="pName" class="form-label">{{ __('admin/databases.name') }}</label>
                        <input type="text" name="name" id="pName" class="form-control" />
                        <p class="text-muted small">{!! __('admin/databases.name_help') !!}</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="pHost" class="form-label">{{ __('admin/databases.host') }}</label>
                            <input type="text" name="host" id="pHost" class="form-control" />
                            <p class="text-muted small">{!! __('admin/databases.host_help') !!}</p>
                        </div>
                        <div class="col-md-6">
                            <label for="pPort" class="form-label">{{ __('admin/databases.port') }}</label>
                            <input type="text" name="port" id="pPort" class="form-control" value="3306"/>
                            <p class="text-muted small">{{ __('admin/databases.port_help') }}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="pUsername" class="form-label">{{ __('admin/databases.username') }}</label>
                            <input type="text" name="username" id="pUsername" class="form-control" />
                            <p class="text-muted small">{{ __('admin/databases.username_help') }}</p>
                        </div>
                        <div class="col-md-6">
                            <label for="pPassword" class="form-label">{{ __('admin/databases.password') }}</label>
                            <input type="password" name="password" id="pPassword" class="form-control" />
                            <p class="text-muted small">{{ __('admin/databases.password_help') }}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pNodeId" class="form-label">{{ __('admin/databases.linked_node') }}</label>
                        <select name="node_id" id="pNodeId" class="form-control">
                            <option value="">{{ __('admin/common.none') }}</option>
                            @foreach($locations as $location)
                                <optgroup label="{{ $location->short }}">
                                    @foreach($location->nodes as $node)
                                        <option value="{{ $node->id }}">{{ $node->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="text-muted small">{{ __('admin/databases.linked_node_help') }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <p class="text-danger small text-left">{!! __('admin/databases.grant_warning') !!}</p>
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">{{ __('admin/common.actions.cancel') }}</button>
                    <button type="submit" class="btn btn-success btn-sm">{{ __('admin/common.actions.create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#pNodeId').select2();
    </script>
@endsection
