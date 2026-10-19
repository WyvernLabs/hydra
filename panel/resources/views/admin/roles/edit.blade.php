@extends('layouts.admin')

@php
    $current = $role ? \Pterodactyl\Models\AdminRole::normalizePermissions($role->permissions) : [];
    $selected = old('permissions', $current);
    // A role holding permissions the viewer does not have can only be looked at.
    $locked = $role && count(array_diff($current, $grantable)) > 0;
    $canSave = !$locked && Auth::user()->hasAdminPermission($role ? 'roles.update' : 'roles.create');
    $color = old('color', $role->color ?? '#74c236');
@endphp

@section('title')
    {{ $role ? __('admin/roles.edit_title', ['name' => $role->name]) : __('admin/roles.create_title') }}
@endsection

@section('content-header')
    <h1>{{ $role ? $role->name : __('admin/roles.create_title') }}<small>{{ $role ? __('admin/roles.edit_subtitle') : __('admin/roles.create_subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.roles') }}">{{ __('admin/roles.title') }}</a></li>
        <li class="active">{{ $role ? $role->name : __('admin/common.actions.create') }}</li>
    </ol>
@endsection

@section('content')
<form action="{{ $role ? route('admin.roles.view', $role->id) : route('admin.roles.new') }}" method="POST" id="roleForm">
    {!! csrf_field() !!}
    @if($role) {!! method_field('PATCH') !!} @endif
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/roles.details') }}</h3>
                </div>
                <div class="box-body">
                    @if($locked)
                        <div class="alert alert-warning">{{ __('admin/roles.locked_notice') }}</div>
                    @endif
                    <div class="form-group">
                        <label for="pName" class="control-label">{{ __('admin/roles.name') }}</label>
                        <input type="text" id="pName" name="name" class="form-control" maxlength="64" value="{{ old('name', $role->name ?? '') }}" required @disabled(!$canSave) />
                    </div>
                    <div class="form-group">
                        <label class="control-label">{{ __('admin/roles.color') }}</label>
                        <div class="hydra-color-field">
                            <input type="color" id="roleColorPicker" value="{{ $color }}" @disabled(!$canSave) />
                            <input type="text" class="form-control" id="roleColorInput" name="color" value="{{ $color }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" @disabled(!$canSave) />
                        </div>
                        @if($canSave)
                            <div class="hydra-swatches" style="margin-top: 8px;">
                                @foreach(['#74c236', '#2fb67c', '#3b9eea', '#6c63ff', '#b16cf0', '#e5534b', '#f08a24', '#e3b341', '#1fb5c4', '#e0569a', '#9ca3af'] as $preset)
                                    <button type="button" class="hydra-swatch" data-color="{{ $preset }}" style="background: {{ $preset }}" title="{{ $preset }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="control-label">{{ __('admin/roles.preview') }}</label>
                        <div><span class="hydra-role-badge" id="rolePreview">{{ old('name', $role->name ?? __('admin/roles.name')) }}</span></div>
                    </div>
                    <div class="form-group">
                        <label for="pDescription" class="control-label">{{ __('admin/roles.description') }} <span class="field-optional"></span></label>
                        <textarea id="pDescription" name="description" class="form-control" rows="3" maxlength="500" @disabled(!$canSave)>{{ old('description', $role->description ?? '') }}</textarea>
                    </div>
                    <div class="form-group no-margin-bottom">
                        <label for="pSort" class="control-label">{{ __('admin/roles.sort') }}</label>
                        <input type="number" id="pSort" name="sort" class="form-control" min="0" value="{{ old('sort', $role->sort ?? 0) }}" @disabled(!$canSave) />
                        <p class="text-muted small">{{ __('admin/roles.sort_help') }}</p>
                    </div>
                </div>
                @if($canSave)
                    <div class="box-footer">
                        <button type="submit" class="btn btn-sm btn-primary pull-right">{{ $role ? __('admin/common.actions.save') : __('admin/roles.create') }}</button>
                    </div>
                @endif
            </div>
        </div>
        @if($role)
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/roles.members') }}</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <tbody>
                                @forelse($role->users as $member)
                                    <tr>
                                        <td><a href="{{ route('admin.users.view', $member->id) }}">{{ $member->username }}</a></td>
                                        <td>{{ $member->email }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted">{{ __('admin/roles.no_members') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <p class="text-muted small no-margin">{{ __('admin/roles.members_help') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/roles.permissions') }}</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">{{ __('admin/roles.permissions_help') }}</p>
                    <div class="hydra-permission-grid">
                        @foreach($groups as $area => $actions)
                            <div class="hydra-permission-group" data-area="{{ $area }}">
                                <div class="hydra-permission-group-title">
                                    <span>{{ __("admin/roles.areas.$area") }}</span>
                                    @if($canSave)
                                        <button type="button" class="btn btn-xs btn-default" data-toggle-area="{{ $area }}">{{ __('admin/common.actions.select_all') }}</button>
                                    @endif
                                </div>
                                @foreach($actions as $action)
                                    @php
                                        $key = "$area.$action";
                                        $disabled = !$canSave || !in_array($key, $grantable);
                                    @endphp
                                    <label class="hydra-permission @if($disabled) is-locked @endif">
                                        <input type="checkbox" name="permissions[]" value="{{ $key }}" data-action="{{ $action }}" @checked(in_array($key, $selected)) @disabled($disabled) />
                                        <span>
                                            {{ __("admin/roles.actions.$area.$action.title") }}
                                            <small>{{ __("admin/roles.actions.$area.$action.description") }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
                @if($canSave)
                    <div class="box-footer">
                        <button type="submit" class="btn btn-sm btn-primary pull-right">{{ $role ? __('admin/common.actions.save') : __('admin/roles.create') }}</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</form>
@if($role && !$locked && Auth::user()->hasAdminPermission('roles.delete'))
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/roles.delete_title') }}</h3>
                </div>
                <div class="box-body">
                    <p class="no-margin">{{ __('admin/roles.delete_notice') }}</p>
                </div>
                <div class="box-footer">
                    <form action="{{ route('admin.roles.delete', $role->id) }}" method="POST" id="deleteRoleForm">
                        {!! csrf_field() !!}
                        {!! method_field('DELETE') !!}
                        <button type="submit" class="btn btn-sm btn-danger pull-right">{{ __('admin/roles.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var picker = document.getElementById('roleColorPicker');
            var input = document.getElementById('roleColorInput');
            var name = document.getElementById('pName');
            var preview = document.getElementById('rolePreview');
            var valid = /^#[0-9a-fA-F]{6}$/;

            function textColor(hex) {
                var r = parseInt(hex.substr(1, 2), 16), g = parseInt(hex.substr(3, 2), 16), b = parseInt(hex.substr(5, 2), 16);
                return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#111827' : '#ffffff';
            }

            function apply(color) {
                if (!valid.test(color)) return;
                picker.value = color;
                input.value = color;
                preview.style.background = color;
                preview.style.color = textColor(color);
            }

            picker.addEventListener('input', function () { apply(picker.value); });
            input.addEventListener('input', function () { apply(input.value); });
            name.addEventListener('input', function () { preview.textContent = name.value || name.placeholder; });
            document.querySelectorAll('.hydra-swatch').forEach(function (swatch) {
                swatch.addEventListener('click', function () { apply(swatch.dataset.color); });
            });
            apply(input.value);

            // Every permission of an area needs its "view" permission, so tick it along.
            document.querySelectorAll('.hydra-permission-group').forEach(function (group) {
                var boxes = group.querySelectorAll('input[type=checkbox]:not(:disabled)');
                var view = group.querySelector('input[data-action=view]');
                boxes.forEach(function (box) {
                    box.addEventListener('change', function () {
                        if (box.checked && view && box !== view && !view.disabled) view.checked = true;
                        if (!box.checked && box === view) boxes.forEach(function (other) { other.checked = false; });
                    });
                });
                var toggle = group.querySelector('[data-toggle-area]');
                if (toggle) {
                    toggle.addEventListener('click', function () {
                        var all = Array.prototype.every.call(boxes, function (box) { return box.checked; });
                        boxes.forEach(function (box) { box.checked = !all; });
                    });
                }
            });

            var deleteForm = document.getElementById('deleteRoleForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    swal({
                        title: @json(__('admin/common.are_you_sure')),
                        text: @json(__('admin/roles.delete_confirm')),
                        type: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d9534f',
                        confirmButtonText: @json(__('admin/roles.delete')),
                        cancelButtonText: @json(__('admin/common.actions.cancel'))
                    }, function () { deleteForm.submit(); });
                });
            }
        })();
    </script>
@endsection
