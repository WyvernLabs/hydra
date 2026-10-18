@extends('layouts.admin')

@section('title')
    {{ __('admin/nests.title') }} &rarr; {{ __('admin/eggs.new_title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/eggs.new_title') }}<small>{{ __('admin/eggs.new_subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nests') }}">{{ __('admin/nests.title') }}</a></li>
        <li class="active">{{ __('admin/eggs.new_title') }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.nests.egg.new') }}" method="POST">
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/eggs.configuration') }}</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pNestId" class="form-label">{{ __('admin/eggs.associated_nest') }}</label>
                                <div>
                                    <select name="nest_id" id="pNestId">
                                        @foreach($nests as $nest)
                                            <option value="{{ $nest->id }}" {{ old('nest_id') != $nest->id ?: 'selected' }}>{{ $nest->name }} &lt;{{ $nest->author }}&gt;</option>
                                        @endforeach
                                    </select>
                                    <p class="text-muted small">{{ __('admin/eggs.associated_nest_help') }}</p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="pName" class="form-label">{{ __('admin/eggs.name') }}</label>
                                <input type="text" id="pName" name="name" value="{{ old('name') }}" class="form-control" />
                                <p class="text-muted small">{{ __('admin/eggs.name_help_new') }}</p>
                            </div>
                            <div class="form-group">
                                <label for="pDescription" class="form-label">{{ __('admin/eggs.description') }}</label>
                                <textarea id="pDescription" name="description" class="form-control" rows="8">{{ old('description') }}</textarea>
                                <p class="text-muted small">{{ __('admin/eggs.description_help_new') }}</p>
                            </div>
                            <div class="form-group">
                                <div class="checkbox checkbox-primary no-margin-bottom">
                                    <input id="pForceOutgoingIp" name="force_outgoing_ip" type="checkbox" value="1" {{ \Pterodactyl\Helpers\Utilities::checked('force_outgoing_ip', 0) }} />
                                    <label for="pForceOutgoingIp" class="strong">{{ __('admin/eggs.force_outgoing_ip') }}</label>
                                    <p class="text-muted small">
                                        {{ __('admin/eggs.force_outgoing_ip_help') }}
                                        <br>
                                        <strong>{{ __('admin/eggs.force_outgoing_ip_warning') }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pDockerImage" class="control-label">{{ __('admin/eggs.docker_images') }}</label>
                                <textarea id="pDockerImages" name="docker_images" rows="4" placeholder="ghcr.io/pterodactyl/yolks" class="form-control">{{ old('docker_images') }}</textarea>
                                <p class="text-muted small">{{ __('admin/eggs.docker_images_help_new') }}</p>
                            </div>
                            <div class="form-group">
                                <label for="pStartup" class="control-label">{{ __('admin/eggs.startup') }}</label>
                                <textarea id="pStartup" name="startup" class="form-control" rows="10">{{ old('startup') }}</textarea>
                                <p class="text-muted small">{{ __('admin/eggs.startup_help_new') }}</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigFeatures" class="control-label">{{ __('admin/eggs.features') }}</label>
                                <div>
                                    <select class="form-control" name="features[]" id="pConfigFeatures" multiple>
                                    </select>
                                    <p class="text-muted small">{{ __('admin/eggs.features_help') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/eggs.process') }}</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="alert alert-warning">
                                <p>{{ __('admin/eggs.process_required') }}</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pConfigFrom" class="form-label">{{ __('admin/eggs.copy_from') }}</label>
                                <select name="config_from" id="pConfigFrom" class="form-control">
                                    <option value="">{{ __('admin/common.none') }}</option>
                                </select>
                                <p class="text-muted small">{{ __('admin/eggs.copy_from_help') }}</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigStop" class="form-label">{{ __('admin/eggs.stop') }}</label>
                                <input type="text" id="pConfigStop" name="config_stop" class="form-control" value="{{ old('config_stop') }}" />
                                <p class="text-muted small">{!! __('admin/eggs.stop_help') !!}</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigLogs" class="form-label">{{ __('admin/eggs.logs') }}</label>
                                <textarea data-action="handle-tabs" id="pConfigLogs" name="config_logs" class="form-control" rows="6">{{ old('config_logs') }}</textarea>
                                <p class="text-muted small">{{ __('admin/eggs.logs_help') }}</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pConfigFiles" class="form-label">{{ __('admin/eggs.files') }}</label>
                                <textarea data-action="handle-tabs" id="pConfigFiles" name="config_files" class="form-control" rows="6">{{ old('config_files') }}</textarea>
                                <p class="text-muted small">{{ __('admin/eggs.files_help') }}</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigStartup" class="form-label">{{ __('admin/eggs.start') }}</label>
                                <textarea data-action="handle-tabs" id="pConfigStartup" name="config_startup" class="form-control" rows="6">{{ old('config_startup') }}</textarea>
                                <p class="text-muted small">{{ __('admin/eggs.start_help') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm pull-right">{{ __('admin/common.actions.create') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/lodash/lodash.js') !!}
    <script>
    $(document).ready(function() {
        $('#pNestId').select2().change();
        $('#pConfigFrom').select2();
    });
    $('#pNestId').on('change', function (event) {
        $('#pConfigFrom').html($('<option value="">').text(@json(__('admin/common.none')))).select2({
            data: $.map(_.get(Pterodactyl.nests, $(this).val() + '.eggs', []), function (item) {
                return {
                    id: item.id,
                    text: item.name + ' <' + item.author + '>',
                };
            }),
        });
    });
    $('textarea[data-action="handle-tabs"]').on('keydown', function(event) {
        if (event.keyCode === 9) {
            event.preventDefault();

            var curPos = $(this)[0].selectionStart;
            var prepend = $(this).val().substr(0, curPos);
            var append = $(this).val().substr(curPos);

            $(this).val(prepend + '    ' + append);
        }
    });
    $('#pConfigFeatures').select2({
        tags: true,
        selectOnClose: false,
        tokenSeparators: [',', ' '],
    });
    </script>
@endsection
