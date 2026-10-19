{{-- Hydra: a user's profile picture (or initials) with the online dot. Expects $user, optional $size. --}}
@php
    $size = $size ?? 32;
    $picture = $user->avatarUrl();
@endphp
<span class="hydra-user-avatar" style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.4) }}px;">
    @if($picture)
        <img src="{{ $picture }}" alt="">
    @else
        <span class="hydra-user-avatar-initials">{{ mb_strtoupper(mb_substr($user->username, 0, 1)) }}</span>
    @endif
    @if($user->isOnline())
        <span class="hydra-online-dot hydra-online-dot--small"></span>
    @endif
</span>
