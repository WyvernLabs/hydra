{{-- Hydra: whether a user is online, or when they were last seen. Expects $user. --}}
@if($user->isOnline())
    <span class="label label-success">{{ __('admin/users.online') }}</span>
@elseif($user->last_seen_at)
    <span title="{{ $user->last_seen_at->timezone(config('app.timezone'))->isoFormat('LLL') }}">{{ $user->last_seen_at->diffForHumans() }}</span>
@else
    <span class="text-muted">{{ __('admin/users.never_seen') }}</span>
@endif
