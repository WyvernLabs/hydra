@extends('layouts.admin')

@php
    $heading = $template ? __('admin/server_templates.edit', ['name' => $template->name]) : __('admin/server_templates.new');
    $sent = !is_null(old('_token'));
    $flag = fn (string $field, bool $saved) => $sent ? (bool) old($field) : $saved;
@endphp

@section('title')
    {{ $heading }}
@endsection

@section('content-header')
    <h1>{{ $heading }}<small>{{ __('admin/server_templates.form_subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li><a href="{{ route('admin.servers.templates') }}">{{ __('admin/server_templates.title') }}</a></li>
        <li class="active">{{ $heading }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ $template ? route('admin.servers.templates.view', $template->id) : route('admin.servers.templates.new') }}" method="POST">
    {!! csrf_field() !!}
    @if($template) {!! method_field('PATCH') !!} @endif

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/server_templates.general') }}</h3>
                </div>
                <div class="box-body row">
                    <div class="form-group col-md-6">
                        <label for="pName">{{ __('admin/server_templates.name') }}</label>
                        <input type="text" id="pName" name="name" class="form-control" maxlength="64" value="{{ old('name', $template?->name) }}">
                        <p class="small text-muted no-margin">{{ __('admin/server_templates.name_help') }}</p>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="pDescription">{{ __('admin/server_templates.description') }}</label>
                        <input type="text" id="pDescription" name="description" class="form-control" maxlength="500" value="{{ old('description', $template?->description) }}">
                        <p class="small text-muted no-margin">{{ __('admin/server_templates.description_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/server_templates.target') }}</h3>
                </div>
                <div class="box-body row">
                    <div class="form-group col-md-6">
                        <label for="pLocations">{{ __('admin/server_templates.locations') }}</label>
                        <select id="pLocations" name="locations[]" class="form-control" multiple>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected(in_array($location->id, old('locations', $template?->locations ?? [])))>{{ $location->short }}@if($location->long) – {{ $location->long }}@endif</option>
                            @endforeach
                        </select>
                        <p class="small text-muted no-margin">{{ __('admin/server_templates.locations_help') }}</p>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="pNodes">{{ __('admin/server_templates.nodes') }}</label>
                        <select id="pNodes" name="nodes[]" class="form-control" multiple>
                            @foreach($nodes as $node)
                                <option value="{{ $node->id }}" @selected(in_array($node->id, old('nodes', $template?->nodes ?? [])))>{{ $node->name }}</option>
                            @endforeach
                        </select>
                        <p class="small text-muted no-margin">{{ __('admin/server_templates.nodes_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.resources') }}</h3>
                </div>
                <div class="box-body row">
                    <div class="form-group col-md-4 col-xs-6">
                        <label for="pCPU">{{ __('admin/servers.fields.cpu') }}</label>
                        <div class="input-group">
                            <input type="number" min="0" id="pCPU" name="cpu" class="form-control" value="{{ old('cpu', $template?->cpu ?? 100) }}">
                            <span class="input-group-addon">%</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.cpu_help_new') !!}</p>
                    </div>
                    <div class="form-group col-md-4 col-xs-6">
                        <label for="pMemory">{{ __('admin/servers.fields.memory') }}</label>
                        <div class="input-group">
                            <input type="number" min="0" id="pMemory" name="memory" class="form-control" value="{{ old('memory', $template?->memory ?? 1024) }}">
                            <span class="input-group-addon">MiB</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.memory_help') !!}</p>
                    </div>
                    <div class="form-group col-md-4 col-xs-6">
                        <label for="pDisk">{{ __('admin/servers.fields.disk') }}</label>
                        <div class="input-group">
                            <input type="number" min="0" id="pDisk" name="disk" class="form-control" value="{{ old('disk', $template?->disk ?? 5120) }}">
                            <span class="input-group-addon">MiB</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.disk_help') !!}</p>
                    </div>
                    <div class="form-group col-md-4 col-xs-6">
                        <label for="pSwap">{{ __('admin/servers.fields.swap') }}</label>
                        <div class="input-group">
                            <input type="number" min="-1" id="pSwap" name="swap" class="form-control" value="{{ old('swap', $template?->swap ?? 0) }}">
                            <span class="input-group-addon">MiB</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.swap_help') !!}</p>
                    </div>
                    <div class="form-group col-md-4 col-xs-6">
                        <label for="pIO">{{ __('admin/servers.fields.io') }}</label>
                        <input type="number" min="10" max="1000" id="pIO" name="io" class="form-control" value="{{ old('io', $template?->io ?? 500) }}">
                        <p class="text-muted small">{!! __('admin/servers.fields.io_help_new') !!}</p>
                    </div>
                    <div class="form-group col-md-4 col-xs-6">
                        <label for="pThreads">{{ __('admin/servers.fields.threads') }}</label>
                        <input type="text" id="pThreads" name="threads" class="form-control" value="{{ old('threads', $template?->threads) }}">
                        <p class="text-muted small">{!! __('admin/servers.fields.threads_help') !!}</p>
                    </div>
                    <div class="form-group col-xs-12">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="checkbox" id="pOom" name="oom_enabled" value="1" @checked($flag('oom_enabled', $template ? !$template->oom_disabled : false))>
                            <label for="pOom" class="strong">{{ __('admin/servers.fields.oom_enable') }}</label>
                        </div>
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.oom_help_new') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.feature_limits') }}</h3>
                </div>
                <div class="box-body row">
                    <div class="form-group col-md-4">
                        <label for="pDatabaseLimit">{{ __('admin/servers.fields.database_limit') }}</label>
                        <input type="number" min="0" id="pDatabaseLimit" name="database_limit" class="form-control" value="{{ old('database_limit', $template?->database_limit ?? 0) }}">
                        <p class="text-muted small">{{ __('admin/servers.fields.database_limit_help') }}</p>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="pAllocationLimit">{{ __('admin/servers.fields.allocation_limit') }}</label>
                        <input type="number" min="0" id="pAllocationLimit" name="allocation_limit" class="form-control" value="{{ old('allocation_limit', $template?->allocation_limit ?? 0) }}">
                        <p class="text-muted small">{{ __('admin/servers.fields.allocation_limit_help') }}</p>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="pBackupStorage">{{ __('admin/servers.fields.backup_storage') }}</label>
                        <div class="input-group">
                            <input type="number" min="0" step="0.1" id="pBackupStorage" name="backup_storage_gb" class="form-control" value="{{ old('backup_storage_gb', $template ? round($template->backup_storage_limit / 1024, 2) : 0) }}">
                            <span class="input-group-addon">GB</span>
                        </div>
                        <p class="text-muted small">{{ __('admin/servers.fields.backup_storage_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.nest_configuration') }}</h3>
                </div>
                <div class="box-body row">
                    <div class="form-group col-xs-12">
                        <label for="pEggId">{{ __('admin/servers.fields.egg') }}</label>
                        <select id="pEggId" name="egg_id" class="form-control">
                            @foreach($nests as $nest)
                                <optgroup label="{{ $nest->name }}">
                                    @foreach($nest->eggs as $egg)
                                        <option value="{{ $egg->id }}" @selected((int) old('egg_id', $template?->egg_id) === $egg->id)>{{ $egg->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.egg_help_new') }}</p>
                    </div>
                    <div class="form-group col-xs-12">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="checkbox" id="pSkipScripting" name="skip_scripts" value="1" @checked($flag('skip_scripts', (bool) $template?->skip_scripts))>
                            <label for="pSkipScripting" class="strong">{{ __('admin/servers.fields.skip_scripts') }}</label>
                        </div>
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.skip_scripts_help') }}</p>
                    </div>
                    <div class="form-group col-xs-12">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="checkbox" id="pStart" name="start_on_completion" value="1" @checked($flag('start_on_completion', (bool) $template?->start_on_completion))>
                            <label for="pStart" class="strong">{{ __('admin/servers.fields.start_on_completion') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.docker_configuration') }}</h3>
                </div>
                <div class="box-body row">
                    <div class="form-group col-xs-12">
                        <label for="pImage">{{ __('admin/servers.fields.docker_image') }}</label>
                        <select id="pImage" name="image" class="form-control"></select>
                        <input id="pImageCustom" name="custom_image" class="form-control" placeholder="{{ __('admin/servers.fields.custom_image') }}" style="margin-top:1rem">
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.docker_image_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.startup_configuration') }}</h3>
                </div>
                <div class="box-body row">
                    <div class="form-group col-xs-12">
                        <label for="pStartup">{{ __('admin/servers.fields.startup') }}</label>
                        <input type="text" id="pStartup" name="startup" class="form-control" value="{{ old('startup', $template?->startup) }}">
                        <p class="small text-muted no-margin">{{ __('admin/server_templates.startup_help') }}</p>
                    </div>
                </div>
                <div class="box-header with-border" style="margin-top:-10px;">
                    <h3 class="box-title">{{ __('admin/servers.fields.service_variables') }}</h3>
                </div>
                <div class="box-body row" id="pVariables"></div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success pull-right">{{ $template ? __('admin/common.actions.save') : __('admin/common.actions.create') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var eggs = Pterodactyl.templateEggs, saved = Pterodactyl.templateValues;

            function render(initial) {
                var id = parseInt($('#pEggId').val(), 10);
                var egg = eggs.filter(function (item) { return item.id === id; })[0];
                if (!egg) return;

                // The saved image goes into the list if it is one of the egg, otherwise into the own field.
                var known = initial && egg.images.indexOf(saved.image) !== -1;
                var select = $('#pImage').empty();
                egg.images.forEach(function (image) {
                    select.append($('<option>').val(image).text(image));
                });
                if (known) select.val(saved.image);
                $('#pImageCustom').val(initial && !known ? saved.image : '');

                $('#pStartup').attr('placeholder', egg.startup);

                var box = $('#pVariables').empty();
                egg.variables.forEach(function (variable) {
                    var has = initial && Object.prototype.hasOwnProperty.call(saved.environment, variable.env);
                    var value = has ? saved.environment[variable.env] : variable.default;
                    var group = $('<div class="form-group col-sm-6">');
                    group.append($('<label class="control-label">').text(variable.name));
                    group.append($('<input type="text" class="form-control">').attr('name', 'environment[' + variable.env + ']').val(value === null ? '' : value));
                    group.append($('<p class="no-margin small text-muted">').text(variable.description));
                    group.append($('<p class="no-margin small text-muted">').append($('<code>').text(variable.env)).append(' ').append($('<code>').text(variable.rules)));
                    box.append(group);
                });
            }

            $('#pLocations, #pNodes').select2({ width: '100%' });
            $('#pEggId').select2({ width: '100%' }).on('change', function () { render(false); });
            render(true);
        })();
    </script>
@endsection
