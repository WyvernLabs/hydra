@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.firewall.title', ['name' => $node->name]) }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>{{ __('admin/nodes.firewall.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('admin/nodes.title') }}</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">{{ __('admin/nodes.tabs.firewall') }}</li>
    </ol>
@endsection

@section('content')
@include('partials.admin.nodes.tabs', ['active' => 'firewall'])
<div class="row">
    <div class="col-xs-12" id="firewallError" hidden>
        <div class="alert alert-danger">
            <span data-message></span>
            <pre class="hydra-update-log" data-detail hidden style="margin: 10px 0 0;"></pre>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.firewall.status') }}</h3>
                <div class="box-tools">
                    <button type="button" class="btn btn-xs btn-default" id="reloadButton"><i class="fa fa-refresh"></i> {{ __('admin/nodes.firewall.reload') }}</button>
                </div>
            </div>
            <div class="box-body">
                <table class="table table-condensed no-margin">
                    <tr>
                        <th style="width: 50%;">{{ __('admin/nodes.firewall.state') }}</th>
                        <td id="firewallState"><i class="fa fa-spinner fa-pulse"></i></td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/nodes.firewall.incoming') }}</th>
                        <td id="firewallIncoming">—</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/nodes.firewall.outgoing') }}</th>
                        <td id="firewallOutgoing">—</td>
                    </tr>
                </table>
                <p class="text-muted small" id="firewallHint" hidden style="margin: 10px 0 0;"></p>
            </div>
            <div class="box-footer">
                <button type="button" class="btn btn-sm btn-primary" id="installButton" hidden><i class="fa fa-download"></i> {{ __('admin/nodes.firewall.install') }}</button>
                <button type="button" class="btn btn-sm btn-success" id="enableButton" hidden><i class="fa fa-shield"></i> {{ __('admin/nodes.firewall.enable') }}</button>
                <button type="button" class="btn btn-sm btn-danger" id="disableButton" hidden><i class="fa fa-power-off"></i> {{ __('admin/nodes.firewall.disable') }}</button>
            </div>
        </div>
        <div class="callout callout-info">
            <p class="small no-margin">{{ __('admin/nodes.firewall.docker_note') }}</p>
        </div>
    </div>
    <div class="col-md-8">
        <form class="box" id="ruleForm">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.firewall.add') }}</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="form-group col-sm-4">
                        <label class="control-label" for="ruleAction">{{ __('admin/nodes.firewall.action') }}</label>
                        <select class="form-control" id="ruleAction" name="action">
                            @foreach(['allow', 'deny', 'reject', 'limit'] as $action)
                                <option value="{{ $action }}">{{ __('admin/nodes.firewall.actions.' . $action) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-4">
                        <label class="control-label" for="ruleDirection">{{ __('admin/nodes.firewall.direction') }}</label>
                        <select class="form-control" id="ruleDirection" name="direction">
                            <option value="in">{{ __('admin/nodes.firewall.direction_in') }}</option>
                            <option value="out">{{ __('admin/nodes.firewall.direction_out') }}</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-4">
                        <label class="control-label" for="ruleProtocol">{{ __('admin/nodes.firewall.protocol') }}</label>
                        <select class="form-control" id="ruleProtocol" name="protocol">
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                            <option value="any">{{ __('admin/nodes.firewall.protocol_any') }}</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-4">
                        <label class="control-label" for="rulePort">{{ __('admin/nodes.firewall.port') }}</label>
                        <input type="text" class="form-control" id="rulePort" name="port" placeholder="25565" maxlength="64">
                        <p class="help-block small">{!! __('admin/nodes.firewall.port_help') !!}</p>
                    </div>
                    <div class="form-group col-sm-4">
                        <label class="control-label" for="ruleAddress">{{ __('admin/nodes.firewall.address') }}</label>
                        <input type="text" class="form-control" id="ruleAddress" name="address" placeholder="any" maxlength="64">
                        <p class="help-block small">{!! __('admin/nodes.firewall.address_help') !!}</p>
                    </div>
                    <div class="form-group col-sm-4">
                        <label class="control-label" for="ruleComment">{{ __('admin/nodes.firewall.comment') }}</label>
                        <input type="text" class="form-control" id="ruleComment" name="comment" placeholder="{{ __('admin/nodes.firewall.comment_placeholder') }}" maxlength="100">
                    </div>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/nodes.firewall.limit_help') }}</p>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-sm btn-primary pull-right" id="addButton" disabled><i class="fa fa-plus"></i> {{ __('admin/nodes.firewall.add') }}</button>
            </div>
        </form>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.firewall.rules') }}</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 110px;">{{ __('admin/nodes.firewall.action') }}</th>
                            <th>{{ __('admin/nodes.firewall.rule') }}</th>
                            <th>{{ __('admin/nodes.firewall.comment') }}</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="ruleList">
                        <tr><td colspan="4" class="text-center text-muted"><i class="fa fa-spinner fa-pulse"></i></td></tr>
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
            var lang = @json(__('admin/nodes.firewall.js'));
            var text = {
                active: @json(__('admin/nodes.firewall.state_active')),
                inactive: @json(__('admin/nodes.firewall.state_inactive')),
                missing: @json(__('admin/nodes.firewall.state_missing')),
                missingHint: @json(__('admin/nodes.firewall.missing_text')),
                inactiveHint: @json(__('admin/nodes.firewall.inactive_text')),
                noRules: @json(__('admin/nodes.firewall.no_rules')),
                protected: @json(__('admin/nodes.firewall.protected')),
                managed: @json(__('admin/nodes.firewall.managed')),
                actions: @json(__('admin/nodes.firewall.actions')),
                offline: @json(__('admin/nodes.errors.offline')),
            };
            var urls = {
                state: '{{ route('admin.nodes.view.firewall.state', $node->id) }}',
                install: '{{ route('admin.nodes.view.firewall.install', $node->id) }}',
                enable: '{{ route('admin.nodes.view.firewall.enable', $node->id) }}',
                disable: '{{ route('admin.nodes.view.firewall.disable', $node->id) }}',
                rules: '{{ route('admin.nodes.view.firewall.rules', $node->id) }}',
            };
            var token = '{{ csrf_token() }}';
            var rules = [];
            var installed = false;

            function esc(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function showError(xhr) {
                var data = (xhr && xhr.responseJSON) || {};
                var message = data.error;
                if (!message && data.errors) {
                    message = Object.keys(data.errors).map(function (key) { return data.errors[key].join(' '); }).join(' ');
                }
                $('#firewallError [data-message]').text(message || data.message || lang.error_text);
                $('#firewallError [data-detail]').text(data.detail || '').prop('hidden', !data.detail);
                $('#firewallError').prop('hidden', false);
            }

            function hideError() {
                $('#firewallError').prop('hidden', true);
            }

            var labels = { allow: 'success', limit: 'warning', deny: 'danger', reject: 'danger' };

            function render(data) {
                var fw = data.firewall || {};
                installed = !!fw.installed;
                rules = fw.rules || [];

                if (!fw.installed) {
                    $('#firewallState').html('<span class="label label-default">' + esc(text.missing) + '</span>');
                } else {
                    $('#firewallState').html(fw.active
                        ? '<span class="label label-success">' + esc(text.active) + '</span>'
                        : '<span class="label label-warning">' + esc(text.inactive) + '</span>');
                }
                $('#firewallIncoming').html(fw.incoming ? esc(text.actions[fw.incoming] || fw.incoming) : '—');
                $('#firewallOutgoing').html(fw.outgoing ? esc(text.actions[fw.outgoing] || fw.outgoing) : '—');

                var hint = '';
                if (!fw.installed) {
                    hint = text.missingHint;
                } else if (!fw.active) {
                    hint = text.inactiveHint
                        .replace(':ssh', (fw.ssh_ports || [22]).join(', '))
                        .replace(':api', fw.api_port)
                        .replace(':sftp', fw.sftp_port);
                }
                $('#firewallHint').text(hint).prop('hidden', !hint);

                $('#installButton').prop('hidden', fw.installed);
                $('#enableButton').prop('hidden', !fw.installed || fw.active);
                $('#disableButton').prop('hidden', !fw.installed || !fw.active);
                $('#addButton').prop('disabled', !fw.installed);

                if (!rules.length) {
                    $('#ruleList').html('<tr><td colspan="4" class="text-center text-muted">' + esc(text.noRules) + '</td></tr>');
                    return;
                }

                $('#ruleList').html(rules.map(function (rule, index) {
                    var badges = '';
                    if (rule.protected) badges += ' <span class="label label-info" title="' + esc(text.protected) + '"><i class="fa fa-lock"></i></span>';
                    if (rule.managed) badges += ' <span class="label label-primary" title="' + esc(text.managed) + '"><i class="fa fa-database"></i></span>';

                    return '<tr>' +
                        '<td><span class="label label-' + (labels[rule.action] || 'default') + '">' + esc(text.actions[rule.action] || rule.action) + '</span></td>' +
                        '<td><code>' + esc(rule.rule) + '</code>' + badges + '</td>' +
                        '<td>' + esc(rule.comment) + '</td>' +
                        '<td class="text-right"><button type="button" class="btn btn-xs btn-danger" data-delete="' + index + '" title="' + esc(lang.delete) + '"><i class="fa fa-trash-o"></i></button></td>' +
                        '</tr>';
                }).join(''));
            }

            function load() {
                return $.ajax({ url: urls.state, dataType: 'json' })
                    .done(function (data) { render(data); })
                    .fail(function (xhr) {
                        $('#firewallState').html('<span class="label label-danger">' + esc(text.offline) + '</span>');
                        $('#ruleList').html('<tr><td colspan="4" class="text-center text-muted">—</td></tr>');
                        showError(xhr);
                    });
            }

            function send(type, url, data) {
                hideError();
                return $.ajax({ type: type, url: url, data: $.extend({ _token: token }, data || {}) })
                    .fail(showError)
                    .always(load);
            }

            function confirmAction(title, message, type, callback) {
                swal({
                    type: type,
                    title: title,
                    text: message,
                    showCancelButton: true,
                    confirmButtonText: lang.confirm,
                    cancelButtonText: lang.cancel,
                    closeOnConfirm: true,
                }, callback);
            }

            $('#reloadButton').on('click', function () { hideError(); load(); });

            $('#installButton').on('click', function () {
                var button = $(this).prop('disabled', true);
                $('#firewallHint').text(lang.installing).prop('hidden', false);
                send('POST', urls.install).always(function () { button.prop('disabled', false); });
            });

            $('#enableButton').on('click', function () {
                confirmAction(lang.confirm_enable_title, lang.confirm_enable_text, 'warning', function () {
                    send('POST', urls.enable);
                });
            });

            $('#disableButton').on('click', function () {
                confirmAction(lang.confirm_disable_title, lang.confirm_disable_text, 'warning', function () {
                    send('POST', urls.disable);
                });
            });

            $('#ruleList').on('click', '[data-delete]', function () {
                var rule = rules[$(this).data('delete')];
                if (!rule) return;

                var message = lang.confirm_delete_text.replace(':rule', rule.rule);
                if (rule.protected) message += '\n\n' + lang.confirm_delete_protected;
                if (rule.managed) message += '\n\n' + lang.confirm_delete_managed;

                confirmAction(lang.confirm_delete_title, message, rule.protected ? 'error' : 'warning', function () {
                    send('DELETE', urls.rules, { rule: rule.rule });
                });
            });

            $('#ruleForm').on('submit', function (event) {
                event.preventDefault();
                var button = $('#addButton').prop('disabled', true);
                send('POST', urls.rules, {
                    action: $('#ruleAction').val(),
                    direction: $('#ruleDirection').val(),
                    protocol: $('#ruleProtocol').val(),
                    port: $('#rulePort').val().trim(),
                    address: $('#ruleAddress').val().trim(),
                    comment: $('#ruleComment').val().trim(),
                }).done(function () {
                    $('#rulePort, #ruleAddress, #ruleComment').val('');
                }).always(function () { button.prop('disabled', !installed); });
            });

            load();
        })();
    </script>
@endsection
