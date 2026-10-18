@extends('layouts.admin')

@section('title')
    {{ __('admin/nests.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/nests.title') }}<small>{{ __('admin/nests.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/nests.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-danger">
            {!! __('admin/nests.warning') !!}
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nests.list') }}</h3>
                <div class="box-tools">
                    <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#importServiceOptionModal" role="button"><i class="fa fa-upload"></i> {{ __('admin/nests.import_egg') }}</a>
                    <a href="{{ route('admin.nests.new') }}" class="btn btn-primary btn-sm">{{ __('admin/common.actions.create_new') }}</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>{{ __('admin/nests.id') }}</th>
                        <th>{{ __('admin/nests.name') }}</th>
                        <th>{{ __('admin/nests.description') }}</th>
                        <th class="text-center">{{ __('admin/nests.eggs_column') }}</th>
                        <th class="text-center">{{ __('admin/nests.servers_column') }}</th>
                    </tr>
                    @foreach($nests as $nest)
                        <tr>
                            <td class="middle"><code>{{ $nest->id }}</code></td>
                            <td class="middle"><a href="{{ route('admin.nests.view', $nest->id) }}" data-toggle="tooltip" data-placement="right" title="{{ $nest->author }}">{{ $nest->name }}</a></td>
                            <td class="col-xs-6 middle">{{ $nest->description }}</td>
                            <td class="text-center middle">{{ $nest->eggs_count }}</td>
                            <td class="text-center middle">{{ $nest->servers_count }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" tabindex="-1" role="dialog" id="importServiceOptionModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('admin/common.actions.cancel') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">{{ __('admin/nests.import_title') }}</h4>
            </div>
            <form action="{{ route('admin.nests.egg.import') }}" enctype="multipart/form-data" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="control-label" for="pImportFile">{{ __('admin/nests.import_file') }} <span class="field-required"></span></label>
                        <div>
                            <input id="pImportFile" type="file" name="import_file" class="form-control" accept="application/json" />
                            <p class="small text-muted">{!! __('admin/nests.import_file_help') !!}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label" for="pImportToNest">{{ __('admin/nests.import_nest') }} <span class="field-required"></span></label>
                        <div>
                            <select id="pImportToNest" name="import_to_nest">
                                @foreach($nests as $nest)
                                   <option value="{{ $nest->id }}">{{ $nest->name }} &lt;{{ $nest->author }}&gt;</option>
                                @endforeach
                            </select>
                            <p class="small text-muted">{{ __('admin/nests.import_nest_help') }}</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {{ csrf_field() }}
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('admin/common.actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('admin/nests.import') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function() {
            $('#pImportToNest').select2();
        });
    </script>
@endsection
