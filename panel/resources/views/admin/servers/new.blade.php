@extends('layouts.admin')

@section('title')
    {{ __('admin/servers.new.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/servers.new.heading') }}<small>{{ __('admin/servers.new.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li class="active">{{ __('admin/servers.new.heading') }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.servers.new') }}" method="POST">
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.new.core') }}</h3>
                </div>

                <div class="box-body row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="pName">{{ __('admin/servers.server_name') }}</label>
                            <input type="text" class="form-control" id="pName" name="name" value="{{ old('name') }}" placeholder="{{ __('admin/servers.server_name') }}">
                            <p class="small text-muted no-margin">{!! __('admin/servers.fields.name_help') !!}</p>
                        </div>

                        <div class="form-group">
                            <label for="pUserId">{{ __('admin/servers.fields.owner') }}</label>
                            <select id="pUserId" name="owner_id" class="form-control" style="padding-left:0;"></select>
                            <p class="small text-muted no-margin">{{ __('admin/servers.fields.owner_help') }}</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="pDescription" class="control-label">{{ __('admin/servers.fields.description') }}</label>
                            <textarea id="pDescription" name="description" rows="3" class="form-control">{{ old('description') }}</textarea>
                            <p class="text-muted small">{{ __('admin/servers.fields.description_help') }}</p>
                        </div>

                        <div class="form-group">
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="pStartOnCreation" name="start_on_completion" type="checkbox" {{ \Pterodactyl\Helpers\Utilities::checked('start_on_completion', 1) }} />
                                <label for="pStartOnCreation" class="strong">{{ __('admin/servers.fields.start_on_completion') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="overlay" id="allocationLoader" style="display:none;"><i class="fa fa-refresh fa-spin"></i></div>
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.allocation_management') }}</h3>
                </div>

                <div class="box-body row">
                    <div class="form-group col-sm-4">
                        <label for="pNodeId">{{ __('admin/servers.node') }}</label>
                        <select name="node_id" id="pNodeId" class="form-control">
                            @foreach($locations as $location)
                                <optgroup label="{{ $location->long }} ({{ $location->short }})">
                                @foreach($location->nodes as $node)

                                <option value="{{ $node->id }}"
                                    @if($location->id === old('location_id')) selected @endif
                                >{{ $node->name }}</option>

                                @endforeach
                                </optgroup>
                            @endforeach
                        </select>

                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.node_help') }}</p>
                    </div>

                    <div class="form-group col-sm-4">
                        <label for="pAllocation">{{ __('admin/servers.fields.default_allocation') }}</label>
                        <select id="pAllocation" name="allocation_id" class="form-control"></select>
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.default_allocation_help') }}</p>
                    </div>

                    <div class="form-group col-sm-4">
                        <label for="pAllocationAdditional">{{ __('admin/servers.fields.additional_allocations') }}</label>
                        <select id="pAllocationAdditional" name="allocation_additional[]" class="form-control" multiple></select>
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.additional_allocations_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="overlay" id="allocationLoader" style="display:none;"><i class="fa fa-refresh fa-spin"></i></div>
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.feature_limits') }}</h3>
                </div>

                <div class="box-body row">
                    <div class="form-group col-xs-6">
                        <label for="pDatabaseLimit" class="control-label">{{ __('admin/servers.fields.database_limit') }}</label>
                        <div>
                            <input type="text" id="pDatabaseLimit" name="database_limit" class="form-control" value="{{ old('database_limit', 0) }}"/>
                        </div>
                        <p class="text-muted small">{{ __('admin/servers.fields.database_limit_help') }}</p>
                    </div>
                    <div class="form-group col-xs-6">
                        <label for="pAllocationLimit" class="control-label">{{ __('admin/servers.fields.allocation_limit') }}</label>
                        <div>
                            <input type="text" id="pAllocationLimit" name="allocation_limit" class="form-control" value="{{ old('allocation_limit', 0) }}"/>
                        </div>
                        <p class="text-muted small">{{ __('admin/servers.fields.allocation_limit_help') }}</p>
                    </div>
                    <div class="form-group col-xs-6">
                        <label for="pBackupStorage" class="control-label">{{ __('admin/servers.fields.backup_storage') }}</label>
                        <div class="input-group">
                            <input type="number" min="0" step="0.1" id="pBackupStorage" name="backup_storage_gb" class="form-control" value="{{ old('backup_storage_gb', 0) }}"/>
                            <span class="input-group-addon">GB</span>
                        </div>
                        {{-- Hydra: the number of backups is no longer limited, kept for the API. --}}
                        <input type="hidden" name="backup_limit" value="0"/>
                        <p class="text-muted small">{{ __('admin/servers.fields.backup_storage_help') }}</p>
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
                    <div class="form-group col-xs-6">
                        <label for="pCPU">{{ __('admin/servers.fields.cpu') }}</label>

                        <div class="input-group">
                            <input type="text" id="pCPU" name="cpu" class="form-control" value="{{ old('cpu', 0) }}" />
                            <span class="input-group-addon">%</span>
                        </div>

                        <p class="text-muted small">{!! __('admin/servers.fields.cpu_help_new') !!}</p>
                    </div>

                    <div class="form-group col-xs-6">
                        <label for="pThreads">{{ __('admin/servers.fields.threads') }}</label>

                        <div>
                            <input type="text" id="pThreads" name="threads" class="form-control" value="{{ old('threads') }}" />
                        </div>

                        <p class="text-muted small">{!! __('admin/servers.fields.threads_help') !!}</p>
                    </div>
                </div>

                <div class="box-body row">
                    <div class="form-group col-xs-6">
                        <label for="pMemory">{{ __('admin/servers.fields.memory') }}</label>

                        <div class="input-group">
                            <input type="text" id="pMemory" name="memory" class="form-control" value="{{ old('memory') }}" />
                            <span class="input-group-addon">MiB</span>
                        </div>

                        <p class="text-muted small">{!! __('admin/servers.fields.memory_help') !!}</p>
                    </div>

                    <div class="form-group col-xs-6">
                        <label for="pSwap">{{ __('admin/servers.fields.swap') }}</label>

                        <div class="input-group">
                            <input type="text" id="pSwap" name="swap" class="form-control" value="{{ old('swap', 0) }}" />
                            <span class="input-group-addon">MiB</span>
                        </div>

                        <p class="text-muted small">{!! __('admin/servers.fields.swap_help') !!}</p>
                    </div>
                </div>

                <div class="box-body row">
                    <div class="form-group col-xs-6">
                        <label for="pDisk">{{ __('admin/servers.fields.disk') }}</label>

                        <div class="input-group">
                            <input type="text" id="pDisk" name="disk" class="form-control" value="{{ old('disk') }}" />
                            <span class="input-group-addon">MiB</span>
                        </div>

                        <p class="text-muted small">{!! __('admin/servers.fields.disk_help') !!}</p>
                    </div>

                    <div class="form-group col-xs-6">
                        <label for="pIO">{{ __('admin/servers.fields.io') }}</label>

                        <div>
                            <input type="text" id="pIO" name="io" class="form-control" value="{{ old('io', 500) }}" />
                        </div>

                        <p class="text-muted small">{!! __('admin/servers.fields.io_help_new') !!}</p>
                    </div>
                    <div class="form-group col-xs-12">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="checkbox" id="pOomDisabled" name="oom_disabled" value="0" {{ \Pterodactyl\Helpers\Utilities::checked('oom_disabled', 0) }} />
                            <label for="pOomDisabled" class="strong">{{ __('admin/servers.fields.oom_enable') }}</label>
                        </div>

                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.oom_help_new') }}</p>
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
                        <label for="pNestId">{{ __('admin/servers.fields.nest') }}</label>

                        <select id="pNestId" name="nest_id" class="form-control">
                            @foreach($nests as $nest)
                                <option value="{{ $nest->id }}"
                                    @if($nest->id === old('nest_id'))
                                        selected="selected"
                                    @endif
                                >{{ $nest->name }}</option>
                            @endforeach
                        </select>

                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.nest_help_new') }}</p>
                    </div>

                    <div class="form-group col-xs-12">
                        <label for="pEggId">{{ __('admin/servers.fields.egg') }}</label>
                        <select id="pEggId" name="egg_id" class="form-control"></select>
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.egg_help_new') }}</p>
                    </div>
                    <div class="form-group col-xs-12">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="checkbox" id="pSkipScripting" name="skip_scripts" value="1" {{ \Pterodactyl\Helpers\Utilities::checked('skip_scripts', 0) }} />
                            <label for="pSkipScripting" class="strong">{{ __('admin/servers.fields.skip_scripts') }}</label>
                        </div>

                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.skip_scripts_help') }}</p>
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
                        <label for="pDefaultContainer">{{ __('admin/servers.fields.docker_image') }}</label>
                        <select id="pDefaultContainer" name="image" class="form-control"></select>
                        <input id="pDefaultContainerCustom" name="custom_image" value="{{ old('custom_image') }}" class="form-control" placeholder="{{ __('admin/servers.fields.custom_image') }}" style="margin-top:1rem"/>
                        <p class="small text-muted no-margin">{{ __('admin/servers.fields.docker_image_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.startup_configuration') }}</h3>
                </div>

                <div class="box-body row">
                    <div class="form-group col-xs-12">
                        <label for="pStartup">{{ __('admin/servers.fields.startup') }}</label>
                        <input type="text" id="pStartup" name="startup" value="{{ old('startup') }}" class="form-control" />
                        <p class="small text-muted no-margin">{!! __('admin/servers.fields.startup_help_new') !!}</p>
                    </div>
                </div>

                <div class="box-header with-border" style="margin-top:-10px;">
                    <h3 class="box-title">{{ __('admin/servers.fields.service_variables') }}</h3>
                </div>

                <div class="box-body row" id="appendVariablesTo"></div>

                <div class="box-footer">
                    {!! csrf_field() !!}
                    <input type="submit" class="btn btn-success pull-right" value="{{ __('admin/servers.new.create') }}" />
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/lodash/lodash.js') !!}

    <script type="application/javascript">
        // Persist 'Service Variables'
        function serviceVariablesUpdated(eggId, ids) {
            @if (old('egg_id'))
                // Check if the egg id matches.
                if (eggId != '{{ old('egg_id') }}') {
                    return;
                }

                @if (old('environment'))
                    @foreach (old('environment') as $key => $value)
                        $('#' + ids['{{ $key }}']).val('{{ $value }}');
                    @endforeach
                @endif
            @endif
            @if(old('image'))
                $('#pDefaultContainer').val('{{ old('image') }}');
            @endif
        }
        // END Persist 'Service Variables'
    </script>

    {!! Theme::js('js/admin/new-server.js?v=20261019') !!}

    <script type="application/javascript">
        $(document).ready(function() {
            // Persist 'Server Owner' select2
            @if (old('owner_id'))
                $.ajax({
                    url: '/admin/users/accounts.json?user_id={{ old('owner_id') }}',
                    dataType: 'json',
                }).then(function (data) {
                    initUserIdSelect([ data ]);
                });
            @else
                initUserIdSelect();
            @endif
            // END Persist 'Server Owner' select2

            // Persist 'Node' select2
            @if (old('node_id'))
                $('#pNodeId').val('{{ old('node_id') }}').change();

                // Persist 'Default Allocation' select2
                @if (old('allocation_id'))
                    $('#pAllocation').val('{{ old('allocation_id') }}').change();
                @endif
                // END Persist 'Default Allocation' select2

                // Persist 'Additional Allocations' select2
                @if (old('allocation_additional'))
                    const additional_allocations = [];

                    @for ($i = 0; $i < count(old('allocation_additional')); $i++)
                        additional_allocations.push('{{ old('allocation_additional.'.$i)}}');
                    @endfor

                    $('#pAllocationAdditional').val(additional_allocations).change();
                @endif
                // END Persist 'Additional Allocations' select2
            @endif
            // END Persist 'Node' select2

            // Persist 'Nest' select2
            @if (old('nest_id'))
                $('#pNestId').val('{{ old('nest_id') }}').change();

                // Persist 'Egg' select2
                @if (old('egg_id'))
                    $('#pEggId').val('{{ old('egg_id') }}').change();
                @endif
                // END Persist 'Egg' select2
            @endif
            // END Persist 'Nest' select2
        });
    </script>
@endsection
