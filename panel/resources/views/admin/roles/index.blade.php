@extends('layouts.admin')

@section('title')
    {{ __('admin/roles.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/roles.title') }}<small>{{ __('admin/roles.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/roles.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/roles.list') }}</h3>
                @if(Auth::user()->hasAdminPermission('roles.create'))
                    <div class="box-tools">
                        <a href="{{ route('admin.roles.new') }}" class="btn btn-sm btn-primary">{{ __('admin/common.actions.create_new') }}</a>
                    </div>
                @endif
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('admin/roles.name') }}</th>
                            <th>{{ __('admin/roles.description') }}</th>
                            <th class="text-center">{{ __('admin/roles.permissions') }}</th>
                            <th class="text-center">{{ __('admin/roles.members') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.roles.view', $role->id) }}">
                                        <span class="hydra-role-badge" style="background: {{ $role->color }}; color: {{ $role->textColor() }};">{{ $role->name }}</span>
                                    </a>
                                </td>
                                <td>{{ str_limit($role->description, 90) }}</td>
                                <td class="text-center">{{ count(\Pterodactyl\Models\AdminRole::normalizePermissions($role->permissions)) }} / {{ count(\Pterodactyl\Models\AdminRole::allPermissions()) }}</td>
                                <td class="text-center"><a href="{{ route('admin.users', ['filter[role]' => $role->id]) }}">{{ $role->users_count }}</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">{{ __('admin/roles.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="callout callout-info">
            {{ __('admin/roles.root_admin_notice') }}
        </div>
    </div>
</div>
@endsection
