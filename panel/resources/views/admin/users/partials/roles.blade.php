{{--
    Hydra: root admin flag and admin roles of a user.
    Expects $roles, $assignableRoles, optionally $user (null when creating) and $readonly.
--}}
@php
    $actor = Auth::user();
    $readonly = $readonly ?? false;
    $isRoot = $user->root_admin ?? false;
    $held = isset($user) ? $user->adminRoles->pluck('id')->all() : [];
    $checked = array_map('intval', old('roles', $held));
    $canAssign = !$readonly && $actor->hasAdminPermission('roles.assign');
@endphp
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">{{ __('admin/users.permissions') }}</h3>
        @if($actor->hasAdminPermission('roles.view'))
            <div class="box-tools"><a href="{{ route('admin.roles') }}" class="btn btn-xs btn-default">{{ __('admin/roles.manage_roles') }}</a></div>
        @endif
    </div>
    <div class="box-body">
        <div class="form-group">
            <label for="root_admin" class="control-label">{{ __('admin/roles.root_admin') }}</label>
            @if($actor->root_admin && !$readonly)
                <select name="root_admin" id="root_admin" class="form-control">
                    <option value="0">@lang('strings.no')</option>
                    <option value="1" @selected(old('root_admin', $isRoot))>@lang('strings.yes')</option>
                </select>
            @else
                <p class="form-control-static">{{ $isRoot ? __('strings.yes') : __('strings.no') }}</p>
            @endif
            <p class="text-muted"><small>{{ __('admin/users.administrator_help') }}</small></p>
        </div>
        <div class="form-group no-margin-bottom hydra-role-list">
            <label class="control-label">{{ __('admin/roles.user_roles') }}</label>
            @if($canAssign)
                <input type="hidden" name="roles_submitted" value="1">
            @endif
            @forelse($roles as $role)
                @php $editable = $canAssign && in_array($role->id, $assignableRoles); @endphp
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="role_{{ $role->id }}" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, $checked)) @disabled(!$editable)>
                    <label for="role_{{ $role->id }}">
                        <span class="hydra-role-badge" style="background: {{ $role->color }}; color: {{ $role->textColor() }};">{{ $role->name }}</span>
                        @if($role->description)<small class="text-muted">{{ $role->description }}</small>@endif
                    </label>
                </div>
            @empty
                <p class="text-muted small">{{ __('admin/roles.user_roles_none') }}</p>
            @endforelse
            <p class="text-muted"><small>{{ $canAssign ? __('admin/roles.user_roles_help') : __('admin/roles.user_roles_readonly') }}</small></p>
        </div>
    </div>
</div>
