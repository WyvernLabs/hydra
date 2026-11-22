@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'domain'])

@php($canUpdate = Auth::user()->hasAdminPermission('domain.update'))
@php($current = $domain['current'])

@section('title')
    {{ __('admin/settings.domain.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/settings.domain.title') }}<small>{{ __('admin/settings.domain.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/settings.domain.title') }}</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    @unless($domain['available'])
        <div class="alert alert-warning">{!! __('admin/settings.domain.not_available') !!}</div>
    @endunless
    <div class="row" id="domainProgressRow" hidden>
        <div class="col-xs-12">
            <div class="box" id="domainProgress">
                <div class="box-body">
                    <p class="no-margin"><i class="fa fa-spinner fa-pulse" id="domainProgressIcon"></i> <strong id="domainProgressText"></strong></p>
                    <p class="text-muted small no-margin" id="domainProgressNote" hidden></p>
                    <p class="no-margin" id="domainProgressLink" hidden style="margin-top: 10px;">
                        <a class="btn btn-sm btn-primary" href="#" id="domainProgressOpen"></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.domain.current') }}</h3>
                </div>
                <div class="box-body">
                    <table class="table table-condensed no-margin">
                        <tr>
                            <th style="width: 40%;">{{ __('admin/settings.domain.address') }}</th>
                            <td><a href="{{ $current['url'] }}"><code>{{ $current['url'] }}</code></a></td>
                        </tr>
                        <tr>
                            <th>{{ __('admin/settings.domain.https') }}</th>
                            <td>
                                @if($current['ssl'])
                                    <span class="label label-success">{{ __('admin/settings.domain.https_on') }}</span>
                                @else
                                    <span class="label label-default">{{ __('admin/settings.domain.https_off') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('admin/settings.domain.redirects') }}</th>
                            <td>
                                @forelse($current['redirects'] as $redirect)
                                    <div style="margin-bottom: 4px;">
                                        <code>{{ $redirect }}</code>
                                        @if($canUpdate && $domain['available'])
                                            <button type="button" class="btn btn-xs btn-default" data-drop-redirect="{{ $redirect }}" title="{{ __('admin/settings.domain.remove') }}">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-muted">{{ __('admin/settings.domain.no_redirects') }}</span>
                                @endforelse
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('admin/settings.domain.state') }}</th>
                            <td id="domainState"></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.domain.hints') }}</h3>
                </div>
                <div class="box-body">
                    <ul class="small no-margin" style="padding-left: 18px;">
                        @foreach(__('admin/settings.domain.hint_list') as $hint)
                            <li>{!! $hint !!}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            @if($canUpdate)
                <form class="box" id="domainForm" autocomplete="off">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/settings.domain.change') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label" for="domainInput">{{ __('admin/settings.domain.new_domain') }}</label>
                            <input type="text" class="form-control" id="domainInput" name="domain" maxlength="253" placeholder="panel.example.com" spellcheck="false" required>
                            <p class="small no-margin" id="domainDns" style="margin-top: 5px;" hidden></p>
                            <p class="text-muted small">{{ __('admin/settings.domain.new_domain_help') }}</p>
                        </div>
                        <div class="form-group">
                            <label class="control-label" for="domainSsl">{{ __('admin/settings.domain.https') }}</label>
                            <select class="form-control" id="domainSsl" name="ssl">
                                @foreach(\Pterodactyl\Services\Hydra\DomainService::SSL_MODES as $mode)
                                    <option value="{{ $mode }}">{{ __("admin/settings.domain.ssl_$mode") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input type="checkbox" id="domainRedirectOld" name="redirect_old" value="1" checked>
                                <label for="domainRedirectOld">{{ __('admin/settings.domain.redirect_old', ['domain' => $current['domain']]) }}</label>
                            </div>
                            <p class="text-muted small">{{ __('admin/settings.domain.redirect_old_help') }}</p>
                        </div>
                        <div class="form-group no-margin-bottom">
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input type="checkbox" id="domainForce" name="force" value="1">
                                <label for="domainForce">{{ __('admin/settings.domain.force') }}</label>
                            </div>
                            <p class="text-muted small no-margin">{{ __('admin/settings.domain.force_help') }}</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-sm btn-primary pull-right" id="domainSubmit" @disabled(!$domain['available'])>
                            <i class="fa fa-globe"></i> {{ __('admin/settings.domain.submit') }}
                        </button>
                    </div>
                </form>
            @endif
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.domain.log') }}</h3>
                </div>
                <div class="box-body">
                    <pre class="hydra-update-log" id="domainLog">{{ $domain['log'] ?: __('admin/settings.domain.no_log') }}</pre>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/settings.domain.nodes') }}</h3>
                    @if($canUpdate && $nodes->isNotEmpty())
                        <div class="box-tools">
                            <button type="button" class="btn btn-sm btn-default" id="domainSyncNodes">
                                <i class="fa fa-refresh"></i> {{ __('admin/settings.domain.sync') }}
                            </button>
                        </div>
                    @endif
                </div>
                <div class="box-body">
                    <p class="text-muted small no-margin">{{ __('admin/settings.domain.nodes_help') }}</p>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('admin/settings.domain.node') }}</th>
                                <th>{{ __('admin/settings.domain.state') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($nodes as $node)
                                <tr data-node="{{ $node->id }}">
                                    <td style="width: 35%;"><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a><br><small class="text-muted">{{ $node->fqdn }}</small></td>
                                    <td data-node-state></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">{{ __('admin/settings.domain.no_nodes') }}</td></tr>
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
            var lang = @json(__('admin/settings.domain.js'));
            var token = '{{ csrf_token() }}';
            var urls = {
                status: '{{ route('admin.settings.domain.status') }}',
                lookup: '{{ route('admin.settings.domain.lookup') }}',
                update: '{{ route('admin.settings.domain.update') }}',
                nodes: '{{ route('admin.settings.domain.nodes') }}',
            };
            var current = @json($current);
            var state = @json($domain);
            // Set while a change requested on this page is running.
            var pending = null;
            var failures = 0;

            function esc(text) {
                return $('<div>').text(text == null ? '' : String(text)).html();
            }

            function badge(type, text) {
                return '<span class="label label-' + type + '">' + esc(text) + '</span>';
            }

            function format(text, values) {
                return String(text).replace(/:(\w+)/g, function (match, key) {
                    return values.hasOwnProperty(key) ? values[key] : match;
                });
            }

            // ---------------------------------------------------------------- State

            function drawState(data) {
                var status = data.status || {};
                var html = '<span class="text-muted">' + esc(lang.never) + '</span>';
                if (data.busy) {
                    html = badge('warning', status.state === 'running' ? lang.running : lang.waiting);
                } else if (status.state === 'success') {
                    html = badge('success', lang.success) + ' <small class="text-muted">' + esc(status.domain || '') + '</small>';
                } else if (status.state === 'failed') {
                    html = badge('danger', lang.failed) + ' <small class="text-muted">' + esc(status.domain || '') + '</small>';
                }
                $('#domainState').html(html);
                $('#domainLog').text(data.log || lang.no_log);
                $('#domainSubmit, [data-drop-redirect]').prop('disabled', !data.available || data.busy);
                drawNodes(data.nodes);
            }

            function drawNodes(result) {
                var byId = {};
                ((result && result.nodes) || []).forEach(function (node) { byId[node.id] = node; });
                $('tr[data-node]').each(function () {
                    var node = byId[$(this).data('node')];
                    var cell = $(this).find('[data-node-state]');
                    if (!node) {
                        cell.html('<span class="text-muted">' + esc(lang.node_never) + '</span>');
                    } else if (node.ok) {
                        cell.html(badge('success', lang.node_ok) + ' <small class="text-muted"><code>' + esc(result.url) + '</code></small>');
                    } else {
                        var known = (lang.node_codes || {})[node.code];
                        cell.html(badge('danger', known ? known.title : lang.node_error)
                            + '<br><small class="text-muted">' + esc(known ? known.text : (node.error || '')) + '</small>');
                    }
                });
            }

            function showProgress(kind, text, note, url) {
                $('#domainProgressRow').prop('hidden', false);
                $('#domainProgress').removeClass('box-success box-danger box-warning').addClass(
                    kind === 'success' ? 'box-success' : (kind === 'failed' ? 'box-danger' : 'box-warning')
                );
                $('#domainProgressIcon').attr('class', 'fa ' + (kind === 'success' ? 'fa-check text-green' : (kind === 'failed' ? 'fa-times text-red' : 'fa-spinner fa-pulse')));
                $('#domainProgressText').text(text);
                $('#domainProgressNote').text(note || '').prop('hidden', !note);
                if (url) {
                    $('#domainProgressOpen').attr('href', url).text(format(lang.open, { url: url }));
                }
                $('#domainProgressLink').prop('hidden', !url);
            }

            function poll() {
                $.ajax({ url: urls.status, dataType: 'json', cache: false })
                    .done(function (data) {
                        failures = 0;
                        state = data;
                        drawState(data);
                        if (!pending) {
                            return;
                        }
                        var status = data.status || {};
                        if (!data.busy && status.domain === pending.domain && status.state === 'success') {
                            showProgress('success', format(lang.done_title, { url: pending.url }), lang.done_text, pending.url);
                            pending = null;
                        } else if (!data.busy && status.domain === pending.domain && status.state === 'failed') {
                            showProgress('failed', lang.failed_title, lang.failed_text, null);
                            pending = null;
                        } else {
                            showProgress('running', data.busy && status.state === 'running' ? lang.running : lang.waiting, null, null);
                        }
                    })
                    .fail(function () {
                        // Once Caddy has switched, the old address may be gone or
                        // redirect elsewhere, so the page cannot ask any more.
                        if (pending && ++failures >= 2) {
                            showProgress('success', format(lang.done_title, { url: pending.url }), lang.lost, pending.url);
                            pending = null;
                        }
                    })
                    .always(function () {
                        if (pending || state.busy) {
                            setTimeout(poll, 2000);
                        }
                    });
            }

            function request(data, url) {
                data._token = token;
                return $.ajax({ type: 'POST', url: urls.update, data: data, dataType: 'json' })
                    .done(function (response) {
                        pending = { domain: data.domain, url: response.url || url };
                        failures = 0;
                        state.busy = true;
                        drawState(state);
                        showProgress('running', lang.waiting, null, null);
                        setTimeout(poll, 1500);
                    })
                    .fail(function (xhr) {
                        var json = xhr.responseJSON || {};
                        var message = json.error;
                        if (!message && json.errors) {
                            message = Object.keys(json.errors).map(function (key) { return json.errors[key][0]; }).join('\n');
                        }
                        if (!message && json.message) {
                            message = json.message;
                        }
                        swal({ type: 'error', title: lang.error, text: message || lang.error_text });
                    });
            }

            // ---------------------------------------------------------------- Form

            var lookupTimer = null;
            var lookupFor = '';

            function normalize(value) {
                return String(value || '').trim().toLowerCase().replace(/^[a-z][a-z0-9+.-]*:\/\//, '').split('/')[0].replace(/\.$/, '');
            }

            function sameAddress(a, b) {
                return a.some(function (ip) { return b.indexOf(ip) !== -1; });
            }

            $('#domainInput').on('input', function () {
                var domain = normalize($(this).val());
                clearTimeout(lookupTimer);
                if (!domain) {
                    $('#domainDns').prop('hidden', true);
                    return;
                }
                $('#domainDns').prop('hidden', false).html('<i class="fa fa-spinner fa-pulse"></i> ' + esc(lang.dns_checking));
                lookupTimer = setTimeout(function () {
                    lookupFor = domain;
                    $.ajax({ url: urls.lookup, data: { domain: domain }, dataType: 'json' }).done(function (data) {
                        if (lookupFor !== domain) {
                            return;
                        }
                        var html;
                        if (!data.valid) {
                            html = '<span class="text-red"><i class="fa fa-times"></i> ' + esc(lang.dns_invalid) + '</span>';
                        } else if (!data.ips.length) {
                            html = '<span class="text-red"><i class="fa fa-times"></i> ' + esc(lang.dns_none) + '</span>';
                        } else if (data.panel_ips.length && sameAddress(data.ips, data.panel_ips)) {
                            html = '<span class="text-green"><i class="fa fa-check"></i> ' + esc(format(lang.dns_same, { ips: data.ips.join(', ') })) + '</span>';
                        } else {
                            html = '<span class="text-yellow"><i class="fa fa-exclamation-triangle"></i> '
                                + esc(format(lang.dns_other, { ips: data.ips.join(', '), panel: data.panel_ips.join(', ') || '-' })) + '</span>';
                        }
                        $('#domainDns').html(html);
                    });
                }, 500);
            });

            $('#domainForm').on('submit', function (event) {
                event.preventDefault();
                var domain = normalize($('#domainInput').val());
                if (!domain) {
                    return;
                }
                var ssl = $('#domainSsl').val();
                var isIp = /^\d{1,3}(\.\d{1,3}){3}$/.test(domain);
                var url = ((ssl === 'on' || (ssl === 'auto' && !isIp)) ? 'https://' : 'http://') + domain;
                swal({
                    type: 'warning',
                    title: lang.confirm_title,
                    text: format(lang.confirm_text, { url: url }),
                    showCancelButton: true,
                    confirmButtonText: lang.confirm_button,
                    cancelButtonText: lang.cancel,
                }, function () {
                    request({
                        domain: domain,
                        ssl: ssl,
                        redirect_old: $('#domainRedirectOld').is(':checked') ? 1 : 0,
                        force: $('#domainForce').is(':checked') ? 1 : 0,
                    }, url);
                });
            });

            $('[data-drop-redirect]').on('click', function () {
                var redirect = $(this).data('drop-redirect');
                swal({
                    type: 'warning',
                    title: lang.remove_title,
                    text: format(lang.remove_text, { domain: redirect }),
                    showCancelButton: true,
                    confirmButtonText: lang.remove_button,
                    cancelButtonText: lang.cancel,
                }, function () {
                    request({ domain: current.domain, ssl: current.ssl ? 'on' : 'off', drop: [redirect] }, current.url);
                });
            });

            $('#domainSyncNodes').on('click', function () {
                var button = $(this).prop('disabled', true);
                $('tr[data-node] [data-node-state]').html('<i class="fa fa-spinner fa-pulse"></i>');
                $.ajax({ type: 'POST', url: urls.nodes, data: { _token: token }, dataType: 'json' })
                    .done(function (data) { drawNodes({ url: current.url, nodes: data.nodes }); })
                    .fail(function () {
                        swal({ type: 'error', title: lang.error, text: lang.error_text });
                        drawNodes(state.nodes);
                    })
                    .always(function () { button.prop('disabled', false); });
            });

            drawState(state);
            if (state.busy) {
                setTimeout(poll, 2000);
            }
        })();
    </script>
@endsection
