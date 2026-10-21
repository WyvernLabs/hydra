{{-- Hydra: installation of phpMyAdmin on the panel server. Expects $addon. --}}
@php($phpMyAdmin = app(\Pterodactyl\Services\Hydra\PhpMyAdminService::class)->toArray())
<div class="row">
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.phpmyadmin.installation') }}</h3>
            </div>
            <div class="box-body">
                @unless($phpMyAdmin['available'])
                    <div class="alert alert-warning">{!! __('admin/addons.phpmyadmin.not_available') !!}</div>
                @endunless
                <table class="table table-condensed no-margin">
                    <tr>
                        <th style="width: 40%;">{{ __('admin/addons.phpmyadmin.state') }}</th>
                        <td id="pmaState"></td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/addons.phpmyadmin.version') }}</th>
                        <td id="pmaVersion">—</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/addons.phpmyadmin.address') }}</th>
                        <td><code>{{ url(\Pterodactyl\Services\Hydra\PhpMyAdminService::PATH) }}/</code></td>
                    </tr>
                </table>
                <p class="text-muted small" style="margin: 10px 0 0;">{{ __('admin/addons.phpmyadmin.help') }}</p>
            </div>
            @if(Auth::user()->hasAdminPermission('addons.update'))
                <div class="box-footer">
                    <button type="button" class="btn btn-sm btn-danger" id="pmaUninstall" hidden><i class="fa fa-trash-o"></i> {{ __('admin/addons.phpmyadmin.uninstall') }}</button>
                    <button type="button" class="btn btn-sm btn-primary pull-right" id="pmaInstall" disabled><i class="fa fa-download"></i> <span>{{ __('admin/addons.phpmyadmin.install') }}</span></button>
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.phpmyadmin.log') }}</h3>
            </div>
            <div class="box-body">
                <pre class="hydra-update-log no-margin" id="pmaLog">{{ $phpMyAdmin['log'] ?: __('admin/addons.phpmyadmin.no_log') }}</pre>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var lang = @json(__('admin/addons.phpmyadmin.js'));
            var urls = {
                status: '{{ route('admin.design.addons.phpmyadmin.status') }}',
                install: '{{ route('admin.design.addons.phpmyadmin.install') }}',
                uninstall: '{{ route('admin.design.addons.phpmyadmin.uninstall') }}',
            };
            var token = '{{ csrf_token() }}';
            var watching = false;

            function badge(type, text) {
                var span = document.createElement('span');
                span.className = 'label label-' + type;
                span.textContent = text;
                return span.outerHTML;
            }

            function render(data) {
                var html;
                if (data.busy) {
                    html = badge('warning', lang.running);
                } else if (data.state === 'failed') {
                    html = badge('danger', lang.failed);
                } else if (data.installed) {
                    html = badge('success', lang.installed);
                } else {
                    html = badge('default', lang.not_installed);
                }
                $('#pmaState').html(html);
                $('#pmaVersion').text(data.version || '—');
                $('#pmaInstall').prop('disabled', !data.available || data.busy)
                    .find('span').text(data.installed ? lang.reinstall : lang.install);
                $('#pmaUninstall').prop('hidden', !data.installed).prop('disabled', data.busy);
                if (data.log) $('#pmaLog').text(data.log);

                if (data.busy) {
                    watching = true;
                    setTimeout(poll, 2000);
                } else if (watching) {
                    // The addon can only be switched on once phpMyAdmin is installed.
                    window.location.reload();
                }
            }

            function poll() {
                $.ajax({ url: urls.status, dataType: 'json' }).done(render);
            }

            function start(url, title, text) {
                swal({
                    type: 'warning',
                    title: title,
                    text: text,
                    showCancelButton: true,
                    confirmButtonText: lang.confirm,
                    cancelButtonText: lang.cancel,
                }, function () {
                    $.ajax({ type: 'POST', url: url, data: { _token: token } })
                        .done(function () {
                            watching = true;
                            $('#pmaLog').text('');
                            poll();
                        })
                        .fail(function (xhr) {
                            swal({ type: 'error', title: lang.error, text: (xhr.responseJSON && xhr.responseJSON.error) || lang.error_text });
                        });
                });
            }

            $('#pmaInstall').on('click', function () { start(urls.install, lang.confirm_install_title, lang.confirm_install_text); });
            $('#pmaUninstall').on('click', function () { start(urls.uninstall, lang.confirm_uninstall_title, lang.confirm_uninstall_text); });

            render(@json(\Illuminate\Support\Arr::except($phpMyAdmin, ['log'])));
        })();
    </script>
@endsection
