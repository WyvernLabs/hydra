@extends('layouts.admin')

@section('title')
    {{ __('admin/api.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/api.title') }}<small>{{ __('admin/api.new_subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.api.index') }}">{{ __('admin/api.title') }}</a></li>
        <li class="active">{{ __('admin/api.new_breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <form method="POST" action="{{ route('admin.api.new') }}">
            <div class="col-sm-8 col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/api.select_permissions') }}</h3>
                        <div class="box-tools">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-default" id="btn-bulk-read">{{ __('admin/api.read_all') }}</button>
                                <button type="button" class="btn btn-sm btn-default" id="btn-bulk-rw">{{ __('admin/api.read_write_all') }}</button>
                                <button type="button" class="btn btn-sm btn-default" id="btn-bulk-none">{{ __('admin/api.none_all') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover" style="min-width: 650px;">
                            @foreach($resources as $resource)
                                <tr>
                                    <td class="strong" style="vertical-align: middle; padding-left: 15px;">
                                        {{ trans()->has('admin/api.resources.' . $resource) ? __('admin/api.resources.' . $resource) : str_replace('_', ' ', title_case($resource)) }}
                                    </td>
                                    
                                    <td class="text-center" style="vertical-align: middle;">
                                        <div class="radio radio-primary" style="margin: 0;">
                                            <input type="radio" id="r_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['r'] }}" @disabled(!($limits[$resource] & $permissions['r']))>
                                            <label for="r_{{ $resource }}">{{ __('admin/api.read') }}</label>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center" style="vertical-align: middle;">
                                        <div class="radio radio-primary" style="margin: 0;">
                                            <input type="radio" id="rw_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['rw'] }}" @disabled($limits[$resource] !== $permissions['rw'])>
                                            <label for="rw_{{ $resource }}">{{ __('admin/api.read_write') }}</label>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center" style="vertical-align: middle;">
                                        <div class="radio" style="margin: 0;">
                                            <input type="radio" id="n_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['n'] }}" checked>
                                            <label for="n_{{ $resource }}">{{ __('admin/api.none') }}</label>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 col-xs-12">
                <div class="box box-primary">
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label" for="memoField">{{ __('admin/api.description') }} <span class="field-required"></span></label>
                            <input id="memoField" type="text" name="memo" class="form-control">
                        </div>
                        <p class="text-muted">{{ __('admin/api.no_edit_notice') }}</p>
                    </div>
                    <div class="box-footer">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-success btn-sm pull-right">{{ __('admin/api.create') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection


@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function() {
            
            function setButtonActive(activeButton) {
                $('#btn-bulk-read, #btn-bulk-rw, #btn-bulk-none')
                    .removeClass('btn-primary')
                    .addClass('btn-default');
                $(activeButton)
                    .removeClass('btn-default')
                    .addClass('btn-primary');
            }

            
            setButtonActive('#btn-bulk-none');

            $('#btn-bulk-read').click(function(e) {
                e.preventDefault();
                $('input[id^="r_"]').prop('checked', true);
                setButtonActive(this); 
            });

            $('#btn-bulk-rw').click(function(e) {
                e.preventDefault();
                $('input[id^="rw_"]').prop('checked', true);
                setButtonActive(this); 
            });

            $('#btn-bulk-none').click(function(e) {
                e.preventDefault();
                $('input[id^="n_"]').prop('checked', true);
                setButtonActive(this); 
            });
            
            
            $('input[type="radio"]').change(function() {
                $('#btn-bulk-read, #btn-bulk-rw, #btn-bulk-none')
                    .removeClass('btn-primary')
                    .addClass('btn-default');
            });
        });
    </script>
@endsection
