@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'console'])

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('title')
    {{ __('admin/design.tabs.console') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.tabs.console') }}<small>{{ __('admin/design.console.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.console') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.console') }}" method="POST">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <div class="row">
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.console.offline_title') }}</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">{{ __('admin/design.console.offline_help') }}</p>
                        <p class="text-muted small">{!! __('admin/design.console.placeholders_help') !!}</p>
                        @php($messages = $console->offlineMessages())
                        @foreach($languages as $code => $name)
                            <div class="form-group @if($loop->last) no-margin @endif">
                                <label for="offline_{{ $code }}" class="control-label">{{ $name }}</label>
                                <textarea id="offline_{{ $code }}" name="offline_message[{{ $code }}]" rows="12" maxlength="10000" wrap="off" spellcheck="false" class="form-control hydra-console-input" data-offline-message @disabled(!$canSave)>{{ old("offline_message.$code", $messages[$code] ?? '') }}</textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.console.prefixes_title') }}</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">{{ __('admin/design.console.prefixes_help') }}</p>
                        <p class="text-muted small">{!! __('admin/design.console.codes_help') !!}</p>
                        <div class="form-group">
                            <label for="consolePrefix" class="control-label">{{ __('admin/design.console.prefix') }}</label>
                            <input type="text" id="consolePrefix" name="prefix" maxlength="64" class="form-control" placeholder="{{ \Pterodactyl\Services\Hydra\ConsoleSettings::DEFAULT_PREFIX }}" value="{{ old('prefix', config('hydra.console.prefix')) }}" @disabled(!$canSave)>
                            <p class="text-muted small no-margin">{{ __('admin/design.console.prefix_help') }}</p>
                        </div>
                        <div class="form-group no-margin">
                            <label for="consoleDaemonPrefix" class="control-label">{{ __('admin/design.console.daemon_prefix') }}</label>
                            <input type="text" id="consoleDaemonPrefix" name="daemon_prefix" maxlength="64" class="form-control" placeholder="[Hydra Daemon]:" value="{{ old('daemon_prefix', $console->daemonPrefix()) }}" @disabled(!$canSave)>
                            <p class="text-muted small no-margin">{{ __('admin/design.console.daemon_prefix_help') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.console.preview') }}</h3>
                    </div>
                    <div class="box-body">
                        <pre class="hydra-console-preview" data-preview></pre>
                        <p class="text-muted small no-margin">{{ __('admin/design.console.preview_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
        @if($canSave)
            <div class="row">
                <div class="col-xs-12">
                    <div class="box">
                        <div class="box-footer">
                            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            // Minecraft color codes, the same as in the console of the client area.
            var colors = {
                '0': '#000000', '1': '#0000aa', '2': '#00aa00', '3': '#00aaaa', '4': '#aa0000', '5': '#aa00aa',
                '6': '#ffaa00', '7': '#aaaaaa', '8': '#555555', '9': '#5555ff', 'a': '#55ff55', 'b': '#55ffff',
                'c': '#ff5555', 'd': '#ff55ff', 'e': '#ffff55', 'f': '#ffffff',
            };
            var example = { name: 'My Server', id: '1a2b3c4d', address: '127.0.0.1:25565', node: 'Node 1' };

            function escape(text) {
                return text.replace(/[&<>"]/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
                });
            }

            // Turns "&a" codes into spans, like the console does with colors.
            function render(text, style) {
                var html = '';
                var open = 0;
                var parts = text.split(/(&[0-9a-fk-or])/i);
                html += '<span style="' + (style || '') + '">';
                open++;
                parts.forEach(function (part) {
                    var match = /^&([0-9a-fk-or])$/i.exec(part);
                    if (!match) {
                        html += escape(part);
                        return;
                    }
                    var code = match[1].toLowerCase();
                    if (code === 'r') {
                        while (open-- > 0) html += '</span>';
                        open = 0;
                        return;
                    }
                    var css = colors[code] ? 'color:' + colors[code]
                        : code === 'l' ? 'font-weight:700'
                        : code === 'o' ? 'font-style:italic'
                        : code === 'n' ? 'text-decoration:underline'
                        : code === 'm' ? 'text-decoration:line-through' : '';
                    html += '<span style="' + css + '">';
                    open++;
                });
                while (open-- > 0) html += '</span>';
                return html;
            }

            function fill(text) {
                return text.replace(/\{(name|id|address|node)\}/g, function (all, key) { return example[key]; });
            }

            var prefix = document.getElementById('consolePrefix');
            var daemon = document.getElementById('consoleDaemonPrefix');
            var messages = document.querySelectorAll('[data-offline-message]');
            var preview = document.querySelector('[data-preview]');
            var prefixStyle = 'color:#faed70;font-weight:700';

            function update() {
                var message = '';
                messages.forEach(function (el) { if (!message && el.value.trim()) message = el.value; });
                var lines = [];
                if (message) {
                    fill(message).replace(/\s+$/, '').split('\n').forEach(function (line) { lines.push(render(line)); });
                }
                lines.push(render(prefix.value || prefix.placeholder, prefixStyle) + ' ' + @json(__('admin/design.console.preview_panel')));
                lines.push(render(daemon.value || daemon.placeholder, prefixStyle) + ' ' + @json(__('admin/design.console.preview_daemon')));
                preview.innerHTML = lines.join('\n');
            }

            [prefix, daemon].concat(Array.prototype.slice.call(messages)).forEach(function (el) {
                el.addEventListener('input', update);
            });
            update();
        })();
    </script>
@endsection
