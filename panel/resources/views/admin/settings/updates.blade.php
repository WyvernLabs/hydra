@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'updates'])

@php($canRun = Auth::user()->hasAdminPermission('updates.run'))

@section('title')
    {{ __('admin/settings.updates.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/settings.updates.title') }}<small>{{ __('admin/settings.updates.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/settings.updates.title') }}</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    @unless($panel['available'])
        <div class="alert alert-warning">{!! __('admin/settings.updates.not_available') !!}</div>
    @endunless
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.updates.panel') }}</h3>
                </div>
                <div class="box-body">
                    <table class="table table-condensed no-margin">
                        <tr>
                            <th style="width: 40%;">{{ __('admin/settings.updates.installed') }}</th>
                            <td id="panelInstalled"></td>
                        </tr>
                        <tr>
                            <th>{{ __('admin/settings.updates.latest') }}</th>
                            <td id="panelLatest"></td>
                        </tr>
                        <tr>
                            <th>{{ __('admin/settings.updates.state') }}</th>
                            <td id="panelState"></td>
                        </tr>
                    </table>
                </div>
                @if($canRun)
                    <div class="box-footer">
                        <button type="button" class="btn btn-sm btn-default" id="checkButton" @disabled(!$panel['available'])>
                            <i class="fa fa-refresh"></i> {{ __('admin/settings.updates.check') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-primary pull-right" id="updatePanelButton" @disabled(!$panel['available'])>
                            <i class="fa fa-download"></i> {{ __('admin/settings.updates.update_panel') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.updates.log') }}</h3>
                </div>
                <div class="box-body">
                    <pre class="hydra-update-log" id="panelLog">{{ $panel['log'] ?: __('admin/settings.updates.no_log') }}</pre>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.updates.nodes') }}</h3>
                    @if($canRun && $nodes->isNotEmpty())
                        <div class="box-tools">
                            <button type="button" class="btn btn-sm btn-primary" id="updateAllNodesButton">
                                <i class="fa fa-download"></i> {{ __('admin/settings.updates.update_all_nodes') }}
                            </button>
                        </div>
                    @endif
                </div>
                <div class="box-body">
                    <p class="text-muted small no-margin">{{ __('admin/settings.updates.nodes_help') }}</p>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('admin/settings.updates.node') }}</th>
                                <th>{{ __('admin/settings.updates.version') }}</th>
                                <th>{{ __('admin/settings.updates.state') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($nodes as $node)
                                <tr data-node="{{ $node->id }}">
                                    <td><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a><br><small class="text-muted">{{ $node->fqdn }}</small></td>
                                    <td data-node-version><i class="fa fa-spinner fa-pulse"></i></td>
                                    <td data-node-state></td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-xs btn-default" data-node-log>{{ __('admin/settings.updates.show_log') }}</button>
                                        @if($canRun)
                                            <button type="button" class="btn btn-xs btn-primary" data-node-update disabled>{{ __('admin/settings.updates.update_node') }}</button>
                                        @endif
                                    </td>
                                </tr>
                                <tr data-node-log-row="{{ $node->id }}" hidden>
                                    <td colspan="4"><pre class="hydra-update-log"></pre></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">{{ __('admin/settings.updates.no_nodes') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var lang = @json(__('admin/settings.updates.js'));
            var urls = {
                status: '{{ route('admin.settings.updates.status') }}',
                check: '{{ route('admin.settings.updates.check') }}',
                panel: '{{ route('admin.settings.updates.panel') }}',
                node: '{{ url('/admin/settings/updates/nodes') }}/',
            };
            var token = '{{ csrf_token() }}';
            var latest = null;
            var panelUpdating = false;

            function esc(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function short(commit) {
                return commit ? String(commit).substr(0, 7) : null;
            }

            function when(date) {
                return date ? new Date(date).toLocaleString() : '';
            }

            function badge(type, text) {
                return '<span class="label label-' + type + '">' + esc(text) + '</span>';
            }

            function commitLine(info) {
                if (!info || !info.commit) return '<span class="text-muted">' + esc(lang.unknown) + '</span>';
                return '<code>' + esc(short(info.commit)) + '</code> ' + esc(info.subject || '') +
                    (info.date ? '<br><small class="text-muted">' + esc(when(info.date)) + '</small>' : '');
            }

            // ---------------------------------------------------------------- Panel

            function renderPanel(data) {
                latest = data.latest && data.latest.commit ? data.latest.commit : null;
                $('#panelInstalled').html(commitLine(data.installed));

                if (data.latest && data.latest.error) {
                    $('#panelLatest').html('<span class="text-danger">' + esc(lang.unreachable) + '</span>');
                } else {
                    $('#panelLatest').html(commitLine(data.latest) + (data.latest && data.latest.checked_at
                        ? '<br><small class="text-muted">' + esc(lang.checked_at) + ' ' + esc(when(data.latest.checked_at)) + '</small>' : ''));
                }

                var state = data.status ? data.status.state : null;
                var html;
                if (data.busy) {
                    html = badge('warning', state === 'running' ? lang.running : lang.waiting);
                } else if (state === 'failed') {
                    html = badge('danger', lang.failed);
                } else if (data.has_update === true) {
                    html = badge('primary', lang.update_available);
                } else if (data.has_update === false) {
                    html = badge('success', lang.up_to_date);
                } else {
                    html = '<span class="text-muted">' + esc(lang.not_checked) + '</span>';
                }
                $('#panelState').html(html);

                $('#checkButton, #updatePanelButton').prop('disabled', !data.available || data.busy);
                if (data.log) $('#panelLog').text(data.log);

                // Finished updates are shown with the new version, so reload once.
                if (panelUpdating && !data.busy && state !== 'running') {
                    panelUpdating = false;
                    window.location.reload();
                }
                refreshNodeButtons();
            }

            function pollPanel() {
                $.ajax({ url: urls.status, dataType: 'json' })
                    .done(renderPanel)
                    .fail(function (xhr) {
                        // While the panel updates itself it is in maintenance mode (503).
                        if (xhr.status === 503) {
                            panelUpdating = true;
                            $('#panelState').html(badge('warning', lang.running));
                        }
                    })
                    .always(function () { setTimeout(pollPanel, panelUpdating ? 3000 : 5000); });
            }

            function post(url) {
                return $.ajax({ type: 'POST', url: url, data: { _token: token } })
                    .fail(function (xhr) {
                        swal({ type: 'error', title: lang.error, text: (xhr.responseJSON && xhr.responseJSON.error) || lang.error_text });
                    });
            }

            $('#checkButton').on('click', function () {
                post(urls.check).done(function () { $('#panelState').html(badge('warning', lang.checking)); });
            });

            $('#updatePanelButton').on('click', function () {
                swal({
                    type: 'warning',
                    title: lang.confirm_panel_title,
                    text: lang.confirm_panel_text,
                    showCancelButton: true,
                    confirmButtonText: lang.confirm_panel_button,
                    cancelButtonText: lang.cancel,
                }, function () {
                    post(urls.panel).done(function () {
                        panelUpdating = true;
                        $('#panelState').html(badge('warning', lang.waiting));
                        $('#panelLog').text('');
                    });
                });
            });

            // ---------------------------------------------------------------- Nodes

            var nodes = {};

            function nodeCommit(version) {
                var match = /\+([0-9a-f]{7,40})$/.exec(version || '');
                return match ? match[1] : null;
            }

            function renderNode(id, data) {
                var row = $('tr[data-node="' + id + '"]');
                nodes[id] = data;

                if (!data.online) {
                    row.find('[data-node-version]').html('<span class="text-muted">—</span>');
                    row.find('[data-node-state]').html(badge('danger', lang.offline));
                    row.find('[data-node-update]').prop('disabled', true);
                    return;
                }

                row.find('[data-node-version]').html('<code>' + esc(data.version) + '</code>');
                var status = data.status || {};
                var commit = nodeCommit(data.version);
                var html;
                if (data.running) {
                    html = badge('warning', lang.running);
                } else if (!data.available) {
                    html = badge('default', lang.node_not_available);
                } else if (status.state === 'failed') {
                    html = badge('danger', lang.failed);
                } else if (latest && commit) {
                    html = latest.indexOf(commit) === 0 ? badge('success', lang.up_to_date) : badge('primary', lang.update_available);
                } else {
                    html = '<span class="text-muted">' + esc(lang.unknown) + '</span>';
                }
                if (status.finished_at && !data.running) {
                    html += '<br><small class="text-muted">' + esc(lang.last_update) + ' ' + esc(when(status.finished_at)) + '</small>';
                }
                row.find('[data-node-state]').html(html);
                row.find('[data-node-update]').prop('disabled', !data.available || data.running);
                $('tr[data-node-log-row="' + id + '"] pre').text(data.log || lang.no_log);
            }

            function pollNode(id) {
                $.ajax({ url: urls.node + id, dataType: 'json' })
                    .done(function (data) { renderNode(id, data); })
                    .always(function () {
                        setTimeout(function () { pollNode(id); }, nodes[id] && nodes[id].running ? 4000 : 15000);
                    });
            }

            function refreshNodeButtons() {
                Object.keys(nodes).forEach(function (id) { renderNode(id, nodes[id]); });
            }

            function updateNode(id) {
                return post(urls.node + id).done(function () {
                    renderNode(id, $.extend({}, nodes[id], { running: true }));
                });
            }

            $('tr[data-node]').each(function () {
                var id = $(this).data('node');
                pollNode(id);
                $(this).find('[data-node-update]').on('click', function () { updateNode(id); });
                $(this).find('[data-node-log]').on('click', function () {
                    var logRow = $('tr[data-node-log-row="' + id + '"]');
                    logRow.prop('hidden', !logRow.prop('hidden'));
                });
            });

            $('#updateAllNodesButton').on('click', function () {
                swal({
                    type: 'warning',
                    title: lang.confirm_nodes_title,
                    text: lang.confirm_nodes_text,
                    showCancelButton: true,
                    confirmButtonText: lang.confirm_nodes_button,
                    cancelButtonText: lang.cancel,
                }, function () {
                    Object.keys(nodes).forEach(function (id) {
                        if (nodes[id].online && nodes[id].available && !nodes[id].running) updateNode(id);
                    });
                });
            });

            renderPanel(@json($panel));
            setTimeout(pollPanel, 5000);
        })();
    </script>
@endsection
