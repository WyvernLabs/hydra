{{-- Hydra: warnings and bans of a user. Expects $user and $canManage. --}}
@php
    $canWarn = Auth::user()->hasAdminPermission('users.warn');
    $canBan = Auth::user()->hasAdminPermission('users.ban');
    $mayAct = $canManage && !Auth::user()->is($user) && !$user->root_admin;
    $activeBan = $user->activeBan();
    $sanctions = $user->sanctions()->with(['issuer', 'lifter'])->latest('id')->limit(50)->get();
    $templates = \Pterodactyl\Models\BanTemplate::query()->orderBy('sort')->orderBy('id')->get();
@endphp
<div class="col-xs-12">
    <div class="box {{ $activeBan ? 'box-danger' : '' }}" id="sanctions">
        <div class="box-header with-border">
            <h3 class="box-title">{{ __('admin/sanctions.title') }}</h3>
            @if($canBan)
                <div class="box-tools">
                    <a href="{{ route('admin.users.ban-templates') }}" class="btn btn-xs btn-default">{{ __('admin/sanctions.templates.title') }}</a>
                </div>
            @endif
        </div>
        <div class="box-body">
            @if($activeBan)
                <div class="alert alert-danger">
                    <strong>{{ $activeBan->expires_at ? __('admin/sanctions.banned_until', ['date' => $activeBan->expires_at->format('d.m.Y H:i')]) : __('admin/sanctions.banned_permanent') }}</strong>
                    <br>{{ $activeBan->reason }}
                    @if($canBan && $mayAct)
                        <form action="{{ route('admin.users.ban.lift', [$user->id, $activeBan->id]) }}" method="POST" style="margin-top: 10px;">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-sm btn-default">{{ __('admin/sanctions.lift') }}</button>
                            <span class="small" style="margin-left: 6px;">{{ __('admin/sanctions.lift_help') }}</span>
                        </form>
                    @endif
                </div>
            @endif

            @if($mayAct && ($canWarn || $canBan))
                <div class="row">
                    @if($canWarn)
                        <div class="col-md-6">
                            <form action="{{ route('admin.users.warn', $user->id) }}" method="POST">
                                {!! csrf_field() !!}
                                <h4>{{ __('admin/sanctions.warn_title') }}</h4>
                                <div class="form-group">
                                    <label class="control-label" for="warnReason">{{ __('admin/sanctions.reason') }}</label>
                                    <textarea id="warnReason" name="reason" class="form-control" rows="3" maxlength="2000" required></textarea>
                                    <p class="text-muted small">{{ __('admin/sanctions.warn_help') }}</p>
                                </div>
                                <button type="submit" class="btn btn-sm btn-warning">{{ __('admin/sanctions.warn') }}</button>
                            </form>
                        </div>
                    @endif
                    @if($canBan)
                        <div class="col-md-6">
                            <form action="{{ route('admin.users.ban', $user->id) }}" method="POST" id="banForm">
                                {!! csrf_field() !!}
                                <h4>{{ __('admin/sanctions.ban_title') }}</h4>
                                <div class="form-group">
                                    <label class="control-label" for="banTemplate">{{ __('admin/sanctions.template') }}</label>
                                    <select id="banTemplate" name="template" class="form-control">
                                        <option value="">{{ __('admin/sanctions.no_template') }}</option>
                                        @foreach($templates as $template)
                                            <option value="{{ $template->id }}" data-reason="{{ $template->reason }}" data-days="{{ $template->duration_days }}">
                                                {{ $template->name }} ({{ $template->duration_days ? trans_choice('admin/sanctions.days', $template->duration_days, ['days' => $template->duration_days]) : __('admin/sanctions.permanent') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="banReason">{{ __('admin/sanctions.reason') }}</label>
                                    <textarea id="banReason" name="reason" class="form-control" rows="3" maxlength="2000"></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="banDays">{{ __('admin/sanctions.duration') }}</label>
                                    <div class="input-group" style="max-width: 240px;">
                                        <input type="number" id="banDays" name="duration_days" class="form-control" min="1" max="3650" placeholder="{{ __('admin/sanctions.permanent') }}">
                                        <span class="input-group-addon">{{ __('admin/sanctions.days_unit') }}</span>
                                    </div>
                                    <input type="hidden" name="permanent" id="banPermanent" value="0">
                                    <p class="text-muted small">{{ __('admin/sanctions.ban_help') }}</p>
                                </div>
                                <button type="submit" class="btn btn-sm btn-danger">{{ $activeBan ? __('admin/sanctions.ban_again') : __('admin/sanctions.ban') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            @elseif(!$mayAct && ($canWarn || $canBan))
                <p class="text-muted small">{{ __('admin/sanctions.not_allowed') }}</p>
            @endif
        </div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('admin/sanctions.type') }}</th>
                        <th>{{ __('admin/sanctions.reason') }}</th>
                        <th>{{ __('admin/sanctions.by') }}</th>
                        <th>{{ __('admin/sanctions.date') }}</th>
                        <th>{{ __('admin/sanctions.state') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sanctions as $sanction)
                        <tr>
                            <td>
                                <span class="label label-{{ $sanction->isBan() ? 'danger' : 'warning' }}">{{ __('admin/sanctions.types.' . $sanction->type) }}</span>
                            </td>
                            <td style="white-space: pre-wrap;">{{ $sanction->reason }}</td>
                            <td>{{ $sanction->issuer?->username ?? '—' }}</td>
                            <td>{{ $sanction->created_at->format('d.m.Y H:i') }}</td>
                            <td class="small">
                                @if($sanction->isBan())
                                    @if($sanction->isActive())
                                        <strong class="text-danger">{{ __('admin/sanctions.state_active') }}</strong>
                                        <br>{{ $sanction->expires_at ? __('admin/sanctions.until', ['date' => $sanction->expires_at->format('d.m.Y H:i')]) : __('admin/sanctions.permanent') }}
                                    @elseif($sanction->lifted_at)
                                        {{ $sanction->lifter ? __('admin/sanctions.lifted_by', ['name' => $sanction->lifter->username, 'date' => $sanction->lifted_at->format('d.m.Y H:i')]) : __('admin/sanctions.expired_at', ['date' => $sanction->lifted_at->format('d.m.Y H:i')]) }}
                                    @else
                                        {{ __('admin/sanctions.expired') }}
                                    @endif
                                @else
                                    {{ $sanction->acknowledged_at ? __('admin/sanctions.acknowledged', ['date' => $sanction->acknowledged_at->format('d.m.Y H:i')]) : __('admin/sanctions.not_acknowledged') }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">{{ __('admin/sanctions.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        (function () {
            // A template fills in reason and duration, both can still be changed.
            $('#banTemplate').on('change', function () {
                var option = $(this).find('option:selected');
                if (!option.val()) return;
                $('#banReason').val(option.data('reason'));
                $('#banDays').val(option.data('days') || '');
            });
            // An empty duration bans permanently, also when the template had one.
            $('#banForm').on('submit', function () {
                $('#banPermanent').val($('#banDays').val() ? '0' : '1');
            });
        })();
    </script>
@endsection
