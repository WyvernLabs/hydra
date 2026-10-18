@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.title') }} &rarr; {{ __('admin/nodes.new.breadcrumb') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/nodes.new.title') }}<small>{{ __('admin/nodes.new.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('admin/nodes.title') }}</a></li>
        <li class="active">{{ __('admin/nodes.new.breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.nodes.new') }}" method="POST">
    <div class="row">
        <div class="col-sm-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/nodes.new.basic') }}</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="form-label">{{ __('admin/nodes.name') }}</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name') }}"/>
                        <p class="text-muted small">{!! __('admin/nodes.name_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="pDescription" class="form-label">{{ __('admin/nodes.description') }}</label>
                        <textarea name="description" id="pDescription" rows="4" class="form-control">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="pLocationId" class="form-label">{{ __('admin/nodes.location') }}</label>
                        <select name="location_id" id="pLocationId">
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ $location->id != old('location_id') ?: 'selected' }}>{{ $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin/nodes.visibility') }}</label>
                        <div>
                            <div class="radio radio-success radio-inline">

                                <input type="radio" id="pPublicTrue" value="1" name="public" checked>
                                <label for="pPublicTrue"> {{ __('admin/nodes.visibility_public') }} </label>
                            </div>
                            <div class="radio radio-danger radio-inline">
                                <input type="radio" id="pPublicFalse" value="0" name="public">
                                <label for="pPublicFalse"> {{ __('admin/nodes.visibility_private') }} </label>
                            </div>
                        </div>
                        <p class="text-muted small">{!! __('admin/nodes.visibility_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label for="pFQDN" class="form-label">{{ __('admin/nodes.fqdn') }}</label>
                        <input type="text" name="fqdn" id="pFQDN" class="form-control" value="{{ old('fqdn') }}"/>
                        <p class="text-muted small">{!! __('admin/nodes.fqdn_help') !!}</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin/nodes.ssl_label') }}</label>
                        <div>
                            <div class="radio radio-success radio-inline">
                                <input type="radio" id="pSSLTrue" value="https" name="scheme" checked>
                                <label for="pSSLTrue"> {{ __('admin/nodes.ssl_use') }}</label>
                            </div>
                            <div class="radio radio-danger radio-inline">
                                <input type="radio" id="pSSLFalse" value="http" name="scheme" @if(request()->isSecure()) disabled @endif>
                                <label for="pSSLFalse"> {{ __('admin/nodes.http_use') }}</label>
                            </div>
                        </div>
                        @if(request()->isSecure())
                            <p class="text-danger small">{!! __('admin/nodes.ssl_required') !!}</p>
                        @else
                            <p class="text-muted small">{{ __('admin/nodes.ssl_help') }}</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('admin/nodes.proxy') }}</label>
                        <div>
                            <div class="radio radio-success radio-inline">
                                <input type="radio" id="pProxyFalse" value="0" name="behind_proxy" checked>
                                <label for="pProxyFalse"> {{ __('admin/nodes.proxy_no') }} </label>
                            </div>
                            <div class="radio radio-info radio-inline">
                                <input type="radio" id="pProxyTrue" value="1" name="behind_proxy">
                                <label for="pProxyTrue"> {{ __('admin/nodes.proxy_yes') }} </label>
                            </div>
                        </div>
                        <p class="text-muted small">{{ __('admin/nodes.proxy_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/nodes.new.configuration') }}</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pDaemonBase" class="form-label">{{ __('admin/nodes.daemon_base') }}</label>
                            <input type="text" name="daemonBase" id="pDaemonBase" class="form-control" value="/var/lib/hydra/volumes" />
                            <p class="text-muted small">{!! __('admin/nodes.daemon_base_help') !!}</p>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pMemory" class="form-label">{{ __('admin/nodes.total_memory') }}</label>
                            <div class="input-group">
                                <input type="text" name="memory" data-multiplicator="true" class="form-control" id="pMemory" value="{{ old('memory') }}"/>
                                <span class="input-group-addon">MiB</span>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pMemoryOverallocate" class="form-label">{{ __('admin/nodes.memory_overallocate') }}</label>
                            <div class="input-group">
                                <input type="text" name="memory_overallocate" class="form-control" id="pMemoryOverallocate" value="{{ old('memory_overallocate') }}"/>
                                <span class="input-group-addon">%</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <p class="text-muted small">{!! __('admin/nodes.memory_help') !!}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pDisk" class="form-label">{{ __('admin/nodes.total_disk') }}</label>
                            <div class="input-group">
                                <input type="text" name="disk" data-multiplicator="true" class="form-control" id="pDisk" value="{{ old('disk') }}"/>
                                <span class="input-group-addon">MiB</span>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pDiskOverallocate" class="form-label">{{ __('admin/nodes.disk_overallocate') }}</label>
                            <div class="input-group">
                                <input type="text" name="disk_overallocate" class="form-control" id="pDiskOverallocate" value="{{ old('disk_overallocate') }}"/>
                                <span class="input-group-addon">%</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <p class="text-muted small">{!! __('admin/nodes.disk_help') !!}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="pDaemonListen" class="form-label">{{ __('admin/nodes.daemon_port') }}</label>
                            <input type="text" name="daemonListen" class="form-control" id="pDaemonListen" value="8080" />
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pDaemonSFTP" class="form-label">{{ __('admin/nodes.daemon_sftp') }}</label>
                            <input type="text" name="daemonSFTP" class="form-control" id="pDaemonSFTP" value="2022" />
                        </div>
                        <div class="col-md-12">
                            <p class="text-muted small">{!! __('admin/nodes.sftp_help_new') !!}</p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success pull-right">{{ __('admin/nodes.new.create') }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#pLocationId').select2();
    </script>
@endsection
