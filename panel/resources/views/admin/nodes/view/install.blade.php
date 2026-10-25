@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.install.title', ['name' => $node->name]) }}
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>{{ __('admin/nodes.install.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.nodes') }}">{{ __('admin/nodes.title') }}</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">{{ __('admin/nodes.tabs.install') }}</li>
    </ol>
@endsection

@section('content')
@include('partials.admin.nodes.tabs', ['active' => 'install'])
@unless($available)
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-warning">{{ __('admin/nodes.install.not_available') }}</div>
        </div>
    </div>
@endunless
<div class="row">
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.install.ssh.title') }}</h3>
            </div>
            <form id="sshForm" autocomplete="off">
                <div class="box-body">
                    <p class="text-muted small">{{ __('admin/nodes.install.ssh.help') }}</p>
                    <div class="row">
                        <div class="form-group col-sm-8">
                            <label for="sshHost" class="control-label">{{ __('admin/nodes.install.ssh.host') }}</label>
                            <input type="text" id="sshHost" name="host" class="form-control" value="{{ $node->fqdn }}" required>
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="sshPort" class="control-label">{{ __('admin/nodes.install.ssh.port') }}</label>
                            <input type="number" id="sshPort" name="port" class="form-control" value="22" min="1" max="65535" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="sshUser" class="control-label">{{ __('admin/nodes.install.ssh.username') }}</label>
                            <input type="text" id="sshUser" name="username" class="form-control" value="root" required>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="sshPassword" class="control-label" data-password-label>{{ __('admin/nodes.install.ssh.password') }}</label>
                            <input type="password" id="sshPassword" name="password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="form-group no-margin">
                        <a href="#" id="toggleKey" class="small">{{ __('admin/nodes.install.ssh.use_key') }}</a>
                        <div id="keyGroup" hidden style="margin-top: 8px;">
                            <label for="sshKey" class="control-label">{{ __('admin/nodes.install.ssh.private_key') }}</label>
                            <textarea id="sshKey" name="private_key" class="form-control" rows="5" spellcheck="false" style="font-family: monospace; font-size: 12px;" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                            <p class="text-muted small no-margin">{{ __('admin/nodes.install.ssh.private_key_help') }}</p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <p class="text-muted small pull-left" style="margin: 7px 0 0; max-width: 70%;">{{ __('admin/nodes.install.ssh.notice') }}</p>
                    <button type="submit" id="sshStart" class="btn btn-sm btn-success pull-right" @disabled(!$available)>
                        <i class="fa fa-download"></i> {{ __('admin/nodes.install.ssh.start') }}
                    </button>
                </div>
            </form>
            <div id="sshProgress" hidden>
                <div class="box-body">
                    <p data-state style="margin-bottom: 10px;"></p>
                    <pre class="hydra-update-log" data-log style="max-height: 420px; overflow: auto; margin: 0;"></pre>
                </div>
                <div class="box-footer" data-done hidden>
                    <a href="{{ route('admin.nodes.view', $node->id) }}" class="btn btn-sm btn-primary" data-success-link hidden>{{ __('admin/nodes.install.ssh.js.to_node') }}</a>
                    <button type="button" class="btn btn-sm btn-default pull-right" id="sshAgain">{{ __('admin/nodes.install.ssh.js.again') }}</button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.install.command.title') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">{{ __('admin/nodes.install.command.help') }}</p>
                <div class="input-group" id="commandGroup" hidden>
                    <input type="text" id="commandText" class="form-control" readonly style="font-family: monospace; font-size: 12px;">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-default" id="commandCopy"><i class="fa fa-copy"></i></button>
                    </span>
                </div>
                <p class="text-muted small no-margin" id="commandValid" hidden style="margin-top: 6px !important;">{{ __('admin/nodes.install.command.valid') }}</p>
            </div>
            <div class="box-footer">
                <button type="button" class="btn btn-sm btn-primary" id="commandButton" @disabled(!$available)>{{ __('admin/nodes.install.command.generate') }}</button>
            </div>
        </div>
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.install.requirements.title') }}</h3>
            </div>
            <div class="box-body">
                <ul class="small" style="padding-left: 18px; margin: 0;">
                    <li>{{ __('admin/nodes.install.requirements.os') }}</li>
                    <li>{{ __('admin/nodes.install.requirements.virtualization') }}</li>
                    <li>{{ __('admin/nodes.install.requirements.panel', ['url' => config('app.url')]) }}</li>
                    @if($certificate)
                        <li>{{ __('admin/nodes.install.requirements.dns', ['fqdn' => $node->fqdn]) }}</li>
                    @else
                        <li>{{ __('admin/nodes.install.requirements.no_certificate') }}</li>
                    @endif
                    <li>{{ __('admin/nodes.install.requirements.duration') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var lang = @json(__('admin/nodes.install.ssh.js'));
            var urls = {
                command: '{{ route('admin.nodes.view.install.command', $node->id) }}',
                ssh: '{{ route('admin.nodes.view.install.ssh', $node->id) }}',
                status: '{{ route('admin.nodes.view.install.status', $node->id) }}',
            };
            var token = '{{ csrf_token() }}';
            var timer = null;

            function error(xhr) {
                return (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || lang.error;
            }

            function render(data) {
                if (!data || data.state === 'none') {
                    $('#sshForm').prop('hidden', false);
                    $('#sshProgress').prop('hidden', true);
                    return;
                }
                $('#sshForm').prop('hidden', true);
                $('#sshProgress').prop('hidden', false);

                var $log = $('#sshProgress [data-log]');
                var atBottom = $log[0].scrollHeight - $log.scrollTop() - $log.outerHeight() < 40;
                $log.text(data.log || lang.waiting);
                if (atBottom) $log.scrollTop($log[0].scrollHeight);

                var text, css;
                if (data.state === 'running') {
                    text = '<i class="fa fa-refresh fa-spin"></i> ' + $('<span>').text(lang.running.replace(':host', data.host || '')).html();
                    css = 'text-primary';
                } else if (data.state === 'success') {
                    text = '<i class="fa fa-check"></i> ' + $('<span>').text(lang.success).html();
                    css = 'text-success';
                } else {
                    text = '<i class="fa fa-times"></i> ' + $('<span>').text(lang.failed.replace(':code', data.exit_code === null || data.exit_code === undefined ? '?' : data.exit_code)).html();
                    css = 'text-danger';
                }
                if (data.error) {
                    text += '<br><small class="text-warning">' + $('<span>').text(data.error).html() + '</small>';
                }
                $('#sshProgress [data-state]').attr('class', css).html(text);

                var done = data.state !== 'running';
                $('#sshProgress [data-done]').prop('hidden', !done);
                $('#sshProgress [data-success-link]').prop('hidden', data.state !== 'success');

                clearTimeout(timer);
                if (!done) timer = setTimeout(poll, 3000);
            }

            function poll() {
                $.get(urls.status).done(render).fail(function () { timer = setTimeout(poll, 5000); });
            }

            $('#toggleKey').on('click', function (event) {
                event.preventDefault();
                var show = $('#keyGroup').prop('hidden');
                $('#keyGroup').prop('hidden', !show);
                $('[data-password-label]').text(show ? lang.passphrase : lang.password);
            });

            $('#sshForm').on('submit', function (event) {
                event.preventDefault();
                var $button = $('#sshStart').prop('disabled', true);
                $.ajax({
                    method: 'POST',
                    url: urls.ssh,
                    headers: { 'X-CSRF-TOKEN': token },
                    data: $(this).serialize(),
                }).done(function (data) {
                    $('#sshPassword, #sshKey').val('');
                    render(data);
                }).fail(function (xhr) {
                    swal({ type: 'error', title: lang.error_title, text: error(xhr) });
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            $('#sshAgain').on('click', function () {
                $.ajax({ method: 'DELETE', url: urls.status, headers: { 'X-CSRF-TOKEN': token } })
                    .always(function () { render({ state: 'none' }); });
            });

            $('#commandButton').on('click', function () {
                var $button = $(this).prop('disabled', true);
                $.ajax({ method: 'POST', url: urls.command, headers: { 'X-CSRF-TOKEN': token } })
                    .done(function (data) {
                        $('#commandText').val(data.command);
                        $('#commandGroup, #commandValid').prop('hidden', false);
                        $button.text(lang.regenerate);
                    })
                    .fail(function (xhr) { swal({ type: 'error', title: lang.error_title, text: error(xhr) }); })
                    .always(function () { $button.prop('disabled', false); });
            });

            $('#commandCopy').on('click', function () {
                var $input = $('#commandText');
                $input.trigger('select');
                (navigator.clipboard ? navigator.clipboard.writeText($input.val()) : Promise.reject())
                    .catch(function () { document.execCommand('copy'); });
                $(this).find('i').attr('class', 'fa fa-check');
                setTimeout(function () { $('#commandCopy i').attr('class', 'fa fa-copy'); }, 1500);
            });

            poll();
        })();
    </script>
@endsection
