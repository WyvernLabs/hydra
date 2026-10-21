@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.database.title', ['name' => $node->name]) }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>{{ __('admin/nodes.database.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('admin/nodes.title') }}</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">{{ __('admin/nodes.tabs.database') }}</li>
    </ol>
@endsection

@section('content')
@include('partials.admin.nodes.tabs', ['active' => 'database'])
<div class="row">
    <div class="col-xs-12" id="databaseError" hidden>
        <div class="alert alert-danger">
            <span data-message></span>
            <pre class="hydra-update-log" data-detail hidden style="margin: 10px 0 0;"></pre>
        </div>
    </div>
    <div class="col-xs-12" id="databaseSuccess" hidden>
        <div class="alert alert-success" data-message></div>
    </div>
</div>
<div class="row">
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.database.server') }}</h3>
            </div>
            <div class="box-body">
                <table class="table table-condensed no-margin">
                    <tr>
                        <th style="width: 40%;">{{ __('admin/nodes.database.installed') }}</th>
                        <td id="serverInstalled"><i class="fa fa-spinner fa-pulse"></i></td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/nodes.database.running') }}</th>
                        <td id="serverRunning">—</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/nodes.database.version') }}</th>
                        <td id="serverVersion" class="small">—</td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.database.host_box') }}</h3>
            </div>
            <div class="box-body" id="hostBox">
                @if($host)
                    <table class="table table-condensed no-margin">
                        <tr><th style="width: 40%;">{{ __('admin/nodes.database.name') }}</th><td>{{ $host->name }}</td></tr>
                        <tr><th>{{ __('admin/nodes.database.host') }}</th><td><code>{{ $host->host }}:{{ $host->port }}</code></td></tr>
                        <tr><th>{{ __('admin/nodes.database.username') }}</th><td><code>{{ $host->username }}</code></td></tr>
                    </table>
                @else
                    <p class="text-muted no-margin">{{ __('admin/nodes.database.no_host') }}</p>
                @endif
            </div>
            @if($host)
                <div class="box-footer">
                    @if(Auth::user()->hasAdminPermission('databases.view'))
                        <a href="{{ route('admin.databases.view', $host->id) }}" class="btn btn-sm btn-default">{{ __('admin/nodes.database.open_host') }}</a>
                    @endif
                    <button type="button" class="btn btn-sm btn-default pull-right" id="syncButton"><i class="fa fa-refresh"></i> {{ __('admin/nodes.database.sync') }}</button>
                    <p class="text-muted small no-margin" style="clear: both; padding-top: 10px;">{{ __('admin/nodes.database.sync_help') }}</p>
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-7">
        <form class="box" id="setupForm">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.database.setup_box') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">{!! __('admin/nodes.database.setup_text') !!}</p>
                <div class="row">
                    <div class="form-group col-sm-12">
                        <label class="control-label" for="setupName">{{ __('admin/nodes.database.name') }}</label>
                        <input type="text" class="form-control" id="setupName" name="name" value="{{ $defaults['name'] }}" maxlength="191" required>
                        <p class="help-block small">{{ __('admin/nodes.database.name_help') }}</p>
                    </div>
                    <div class="form-group col-sm-8">
                        <label class="control-label" for="setupHost">{{ __('admin/nodes.database.host') }}</label>
                        <input type="text" class="form-control" id="setupHost" name="host" value="{{ $defaults['host'] }}" maxlength="191" required>
                        <p class="help-block small">{{ __('admin/nodes.database.host_help') }}</p>
                    </div>
                    <div class="form-group col-sm-4">
                        <label class="control-label" for="setupPort">{{ __('admin/nodes.database.port') }}</label>
                        <input type="number" class="form-control" id="setupPort" name="port" value="{{ $defaults['port'] }}" min="1" max="65535" required>
                    </div>
                    <div class="form-group col-sm-6">
                        <label class="control-label" for="setupSources">{{ __('admin/nodes.database.sources') }}</label>
                        <textarea class="form-control" id="setupSources" name="sources" rows="4">{{ $defaults['sources'] }}</textarea>
                        <p class="help-block small">{{ __('admin/nodes.database.sources_help') }}</p>
                    </div>
                    <div class="form-group col-sm-6">
                        <label class="control-label">{{ __('admin/nodes.database.node_addresses') }}</label>
                        <ul class="list-unstyled small no-margin">
                            @foreach($nodes as $item)
                                <li>
                                    <strong>{{ $item->name }}</strong>:
                                    @if(empty($addresses[$item->id]))
                                        <span class="text-muted">{{ __('admin/nodes.database.no_addresses') }}</span>
                                    @else
                                        <code>{{ implode(', ', $addresses[$item->id]) }}</code>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="form-group col-sm-12 no-margin">
                        <div class="checkbox checkbox-primary no-margin">
                            <input type="checkbox" id="setupFirewall" name="enable_firewall" value="1" checked>
                            <label for="setupFirewall">{{ __('admin/nodes.database.enable_firewall') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-sm btn-primary pull-right" id="setupButton" disabled>
                    <i class="fa fa-magic"></i> {{ $host ? __('admin/nodes.database.rerun') : __('admin/nodes.database.start') }}
                </button>
            </div>
        </form>
        <div class="box" id="progressBox" hidden>
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.database.progress') }}</h3>
            </div>
            <div class="box-body">
                <ul class="list-unstyled" id="progressSteps">
                    @foreach(__('admin/nodes.database.steps') as $key => $label)
                        <li data-step="{{ $key }}"><i class="fa fa-fw fa-circle-o text-muted"></i> {{ $label }}</li>
                    @endforeach
                </ul>
                <pre class="hydra-update-log no-margin" id="progressLog"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var lang = @json(__('admin/nodes.database.js'));
            var errors = @json(__('admin/nodes.errors'));
            var yes = @json(__('admin/nodes.database.yes'));
            var no = @json(__('admin/nodes.database.no'));
            var urls = {
                status: '{{ route('admin.nodes.view.database.status', $node->id) }}',
                setup: '{{ route('admin.nodes.view.database.setup', $node->id) }}',
                sync: '{{ route('admin.nodes.view.database.sync', $node->id) }}',
            };
            var token = '{{ csrf_token() }}';
            var steps = $('#progressSteps [data-step]').map(function () { return $(this).data('step'); }).get();
            var watching = false;
            var hadHost = {{ $host ? 'true' : 'false' }};

            function esc(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function showError(message, detail) {
                $('#databaseSuccess').prop('hidden', true);
                $('#databaseError [data-message]').text(message || lang.error_text);
                $('#databaseError [data-detail]').text(detail || '').prop('hidden', !detail);
                $('#databaseError').prop('hidden', false);
            }

            function showSuccess(message) {
                $('#databaseError').prop('hidden', true);
                $('#databaseSuccess [data-message]').text(message);
                $('#databaseSuccess').prop('hidden', false);
            }

            function xhrError(xhr) {
                var data = (xhr && xhr.responseJSON) || {};
                var message = data.error;
                if (!message && data.errors) {
                    message = Object.keys(data.errors).map(function (key) { return data.errors[key].join(' '); }).join(' ');
                }
                showError(message || data.message, data.detail);
            }

            function renderSteps(setup) {
                var current = steps.indexOf(setup.step);
                $('#progressSteps [data-step]').each(function (index) {
                    var icon = 'fa-circle-o text-muted';
                    if (setup.success || index < current) {
                        icon = 'fa-check-circle text-success';
                    } else if (index === current) {
                        icon = setup.running ? 'fa-spinner fa-pulse' : (setup.error ? 'fa-times-circle text-danger' : icon);
                    }
                    $(this).find('i').attr('class', 'fa fa-fw ' + icon);
                });
                $('#progressLog').text(setup.log || '');
                $('#progressBox').prop('hidden', false);
            }

            function render(data) {
                $('#serverInstalled').text(data.installed ? yes : no);
                $('#serverRunning').text(data.installed ? (data.running ? yes : no) : '—');
                $('#serverVersion').text(data.version || '—');

                var setup = data.setup;
                var running = !!(setup && setup.running);
                $('#setupButton').prop('disabled', running);

                if (setup && (watching || running || data.pending)) {
                    renderSteps(setup);
                }

                if (data.finish_error) {
                    showError(lang.finish_failed + ' ' + data.finish_error);
                } else if (setup && !running && setup.error && watching) {
                    showError(lang.failed + ' ' + (errors[setup.error] || errors.command_failed));
                } else if (setup && setup.success && watching && data.host) {
                    showSuccess(lang.success);
                    // The host box and the button text change once the host exists.
                    if (!hadHost) {
                        setTimeout(function () { window.location.reload(); }, 1500);
                    }
                }

                if (running) {
                    watching = true;
                    setTimeout(poll, 2000);
                } else if (data.pending && setup && setup.success) {
                    setTimeout(poll, 1000);
                } else {
                    watching = false;
                }
            }

            function poll() {
                $.ajax({ url: urls.status, dataType: 'json' })
                    .done(render)
                    .fail(function (xhr) {
                        $('#serverInstalled').text('—');
                        xhrError(xhr);
                    });
            }

            $('#setupForm').on('submit', function (event) {
                event.preventDefault();
                swal({
                    type: 'warning',
                    title: lang.confirm_title,
                    text: lang.confirm_text,
                    showCancelButton: true,
                    confirmButtonText: lang.confirm,
                    cancelButtonText: lang.cancel,
                }, function () {
                    $('#setupButton').prop('disabled', true);
                    $('#databaseError, #databaseSuccess').prop('hidden', true);
                    $.ajax({
                        type: 'POST',
                        url: urls.setup,
                        data: {
                            _token: token,
                            name: $('#setupName').val(),
                            host: $('#setupHost').val().trim(),
                            port: $('#setupPort').val(),
                            sources: $('#setupSources').val(),
                            enable_firewall: $('#setupFirewall').is(':checked') ? 1 : 0,
                        },
                    }).done(function () {
                        watching = true;
                        renderSteps({ step: 'install', running: true, log: lang.running });
                        setTimeout(poll, 1000);
                    }).fail(function (xhr) {
                        $('#setupButton').prop('disabled', false);
                        xhrError(xhr);
                    });
                });
            });

            $('#syncButton').on('click', function () {
                var button = $(this).prop('disabled', true);
                $.ajax({ type: 'POST', url: urls.sync, data: { _token: token }, dataType: 'json' })
                    .done(function (data) {
                        var failed = Object.keys(data.errors || {}).map(function (id) { return data.errors[id]; });
                        if (failed.length) {
                            showError(lang.sync_failed + ' ' + failed.join(' '));
                        } else {
                            showSuccess(lang.synced);
                        }
                    })
                    .fail(xhrError)
                    .always(function () { button.prop('disabled', false); });
            });

            poll();
        })();
    </script>
@endsection
