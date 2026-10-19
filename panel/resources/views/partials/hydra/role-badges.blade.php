{{-- Hydra: the admin roles of a user as colored badges. Expects $user with adminRoles loaded. --}}
@if($user->root_admin)
    <span class="hydra-role-badge hydra-role-badge--root" title="{{ __('admin/roles.root_admin_help') }}"><i class="fa fa-star"></i> {{ __('admin/roles.root_admin') }}</span>
@endif
@foreach($user->adminRoles as $role)
    <span class="hydra-role-badge" style="background: {{ $role->color }}; color: {{ $role->textColor() }};" @if($role->description) title="{{ $role->description }}" @endif>{{ $role->name }}</span>
@endforeach
