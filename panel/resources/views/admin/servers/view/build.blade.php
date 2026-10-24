@extends('layouts.admin')

@section('title')
    {{ __('admin/servers.page_title_tab', ['name' => $server->name, 'tab' => __('admin/servers.tabs.build')]) }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ __('admin/servers.build.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">{{ __('admin/servers.tabs.build') }}</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <form action="{{ route('admin.servers.view.build', $server->id) }}" method="POST">
        <div class="col-sm-5">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/servers.fields.resources') }}</h3>
                </div>
                <div class="box-body">
                <div class="form-group">
                        <label for="cpu" class="control-label">{{ __('admin/servers.fields.cpu') }}</label>
                        <div class="input-group">
                            <input type="text" name="cpu" class="form-control" value="{{ old('cpu', $server->cpu) }}"/>
                            <span class="input-group-addon">%</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.cpu_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="threads" class="control-label">{{ __('admin/servers.fields.threads') }}</label>
                        <div>
                            <input type="text" name="threads" class="form-control" value="{{ old('threads', $server->threads) }}"/>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.threads_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="memory" class="control-label">{{ __('admin/servers.fields.memory_allocated') }}</label>
                        <div class="input-group">
                            <input type="text" name="memory" data-multiplicator="true" class="form-control" value="{{ old('memory', $server->memory) }}"/>
                            <span class="input-group-addon">MiB</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.memory_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="swap" class="control-label">{{ __('admin/servers.fields.swap_allocated') }}</label>
                        <div class="input-group">
                            <input type="text" name="swap" data-multiplicator="true" class="form-control" value="{{ old('swap', $server->swap) }}"/>
                            <span class="input-group-addon">MiB</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.swap_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="cpu" class="control-label">{{ __('admin/servers.fields.disk_limit') }}</label>
                        <div class="input-group">
                            <input type="text" name="disk" class="form-control" value="{{ old('disk', $server->disk) }}"/>
                            <span class="input-group-addon">MiB</span>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.disk_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="io" class="control-label">{{ __('admin/servers.fields.io_proportion') }}</label>
                        <div>
                            <input type="text" name="io" class="form-control" value="{{ old('io', $server->io) }}"/>
                        </div>
                        <p class="text-muted small">{!! __('admin/servers.fields.io_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="cpu" class="control-label">{{ __('admin/servers.fields.oom') }}</label>
                        <div>
                            <div class="radio radio-danger radio-inline">
                                <input type="radio" id="pOomKillerEnabled" value="0" name="oom_disabled" @if(!$server->oom_disabled)checked @endif>
                                <label for="pOomKillerEnabled">{{ __('admin/common.enabled') }}</label>
                            </div>
                            <div class="radio radio-success radio-inline">
                                <input type="radio" id="pOomKillerDisabled" value="1" name="oom_disabled" @if($server->oom_disabled)checked @endif>
                                <label for="pOomKillerDisabled">{{ __('admin/common.disabled') }}</label>
                            </div>
                            <p class="text-muted small">
                                {{ __('admin/servers.fields.oom_help') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-7">
            <div class="row">
                {{-- Hydra: dedicated node, subdomains and reverse proxies. --}}
                <div class="col-xs-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/servers.hydra_network.title') }}</h3>
                        </div>
                        <div class="box-body">
                            <input type="hidden" name="hydra_limits" value="1">
                            <div class="checkbox checkbox-primary no-margin-top">
                                <input type="checkbox" id="hydraDedicated" name="hydra_dedicated" value="1" @checked(old('hydra_dedicated', $server->hydra_dedicated))>
                                <label for="hydraDedicated">{{ __('admin/servers.hydra_network.dedicated') }}</label>
                            </div>
                            <p class="text-muted small">{{ __('admin/servers.hydra_network.dedicated_help') }}</p>
                            <div class="row">
                                <div class="form-group col-xs-6">
                                    <label for="hydraSubdomainLimit" class="control-label">{{ __('admin/servers.hydra_network.subdomain_limit') }}</label>
                                    <input type="number" min="0" id="hydraSubdomainLimit" name="hydra_subdomain_limit" class="form-control" value="{{ old('hydra_subdomain_limit', $server->hydra_subdomain_limit) }}" placeholder="{{ __('admin/servers.hydra_network.default', ['value' => app(\Pterodactyl\Services\Hydra\Addons\SubdomainsAddon::class)->defaultLimit()]) }}">
                                </div>
                                <div class="form-group col-xs-6">
                                    <label for="hydraProxyLimit" class="control-label">{{ __('admin/servers.hydra_network.proxy_limit') }}</label>
                                    <input type="number" min="0" id="hydraProxyLimit" name="hydra_proxy_limit" class="form-control" value="{{ old('hydra_proxy_limit', $server->hydra_proxy_limit) }}" placeholder="{{ __('admin/servers.hydra_network.default', ['value' => app(\Pterodactyl\Services\Hydra\Addons\ProxyAddon::class)->defaultLimit()]) }}">
                                </div>
                            </div>
                            <p class="text-muted small no-margin">{{ __('admin/servers.hydra_network.limits_help') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/servers.fields.feature_limits') }}</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="form-group col-xs-6">
                                    <label for="database_limit" class="control-label">{{ __('admin/servers.fields.database_limit') }}</label>
                                    <div>
                                        <input type="text" name="database_limit" class="form-control" value="{{ old('database_limit', $server->database_limit) }}"/>
                                    </div>
                                    <p class="text-muted small">{{ __('admin/servers.fields.database_limit_help') }}</p>
                                </div>
                                <div class="form-group col-xs-6">
                                    <label for="allocation_limit" class="control-label">{{ __('admin/servers.fields.allocation_limit') }}</label>
                                    <div>
                                        <input type="text" name="allocation_limit" class="form-control" value="{{ old('allocation_limit', $server->allocation_limit) }}"/>
                                    </div>
                                    <p class="text-muted small">{{ __('admin/servers.fields.allocation_limit_help') }}</p>
                                </div>
                                <div class="form-group col-xs-6">
                                    <label for="backup_storage_gb" class="control-label">{{ __('admin/servers.fields.backup_storage') }}</label>
                                    <div class="input-group">
                                        <input type="number" min="0" step="0.1" id="backup_storage_gb" name="backup_storage_gb" class="form-control" value="{{ old('backup_storage_gb', round($server->backup_storage_limit / 1024, 2)) }}"/>
                                        <span class="input-group-addon">GB</span>
                                    </div>
                                    {{-- Hydra: the number of backups is no longer limited, kept for the API. --}}
                                    <input type="hidden" name="backup_limit" value="{{ $server->backup_limit }}"/>
                                    <p class="text-muted small">{{ __('admin/servers.fields.backup_storage_help') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/servers.fields.allocation_management') }}</h3>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <label for="pAllocation" class="control-label">{{ __('admin/servers.build.game_port') }}</label>
                                <select id="pAllocation" name="allocation_id" class="form-control">
                                    @foreach ($assigned as $assignment)
                                        <option value="{{ $assignment->id }}"
                                            @if($assignment->id === $server->allocation_id)
                                                selected="selected"
                                            @endif
                                        >{{ $assignment->alias }}:{{ $assignment->port }}</option>
                                    @endforeach
                                </select>
                                <p class="text-muted small">{{ __('admin/servers.build.game_port_help') }}</p>
                            </div>
                            <div class="form-group">
                                <label for="pAddAllocations" class="control-label">{{ __('admin/servers.build.add_ports') }}</label>
                                <div>
                                    <select name="add_allocations[]" class="form-control" multiple id="pAddAllocations">
                                        @foreach ($unassigned as $assignment)
                                            <option value="{{ $assignment->id }}">{{ $assignment->alias }}:{{ $assignment->port }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <p class="text-muted small">{{ __('admin/servers.build.add_ports_help') }}</p>
                            </div>
                            <div class="form-group">
                                <label for="pRemoveAllocations" class="control-label">{{ __('admin/servers.build.remove_ports') }}</label>
                                <div>
                                    <select name="remove_allocations[]" class="form-control" multiple id="pRemoveAllocations">
                                        @foreach ($assigned as $assignment)
                                            <option value="{{ $assignment->id }}">{{ $assignment->alias }}:{{ $assignment->port }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <p class="text-muted small">{{ __('admin/servers.build.remove_ports_help') }}</p>
                            </div>
                        </div>
                        <div class="box-footer">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-primary pull-right">{{ __('admin/servers.build.update') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#pAddAllocations').select2();
    $('#pRemoveAllocations').select2();
    $('#pAllocation').select2();
    </script>
@endsection
