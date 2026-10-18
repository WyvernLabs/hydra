@extends('layouts.admin')

@section('title')
    {{ __('admin/locations.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/locations.title') }}<small>{{ __('admin/locations.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/locations.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/locations.list') }}</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newLocationModal">{{ __('admin/common.actions.create_new') }}</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>{{ __('admin/locations.id') }}</th>
                            <th>{{ __('admin/locations.short') }}</th>
                            <th>{{ __('admin/locations.description') }}</th>
                            <th class="text-center">{{ __('admin/locations.nodes') }}</th>
                            <th class="text-center">{{ __('admin/locations.servers') }}</th>
                        </tr>
                        @foreach ($locations as $location)
                            <tr>
                                <td><code>{{ $location->id }}</code></td>
                                <td><a href="{{ route('admin.locations.view', $location->id) }}">{{ $location->short }}</a></td>
                                <td>{{ $location->long }}</td>
                                <td class="text-center">{{ $location->nodes_count }}</td>
                                <td class="text-center">{{ $location->servers_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="newLocationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.locations') }}" method="POST">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('admin/common.actions.cancel') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">{{ __('admin/locations.create_title') }}</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <label for="pShortModal" class="form-label">{{ __('admin/locations.short') }}</label>
                            <input type="text" name="short" id="pShortModal" class="form-control" />
                            <p class="text-muted small">{!! __('admin/locations.short_help') !!}</p>
                        </div>
                        <div class="col-md-12">
                            <label for="pLongModal" class="form-label">{{ __('admin/locations.description') }}</label>
                            <textarea name="long" id="pLongModal" class="form-control" rows="4"></textarea>
                            <p class="text-muted small">{{ __('admin/locations.description_help') }}</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default btn-sm pull-left" data-dismiss="modal">{{ __('admin/common.actions.cancel') }}</button>
                    <button type="submit" class="btn btn-success btn-sm">{{ __('admin/common.actions.create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
