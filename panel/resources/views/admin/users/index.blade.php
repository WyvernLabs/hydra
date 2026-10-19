@extends('layouts.admin')

@section('title')
    {{ __('admin/users.list_title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/users.title') }}<small>{{ __('admin/users.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/users.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/users.list') }}</h3>
                <div class="box-tools search01">
                    <form action="{{ route('admin.users') }}" method="GET">
                        <div class="input-group input-group-sm">
                            @if($roles->isNotEmpty())
                                <select name="filter[role]" class="form-control" onchange="this.form.submit()" title="{{ __('admin/roles.filter_role') }}" style="width: auto; float: left;">
                                    <option value="">{{ __('admin/roles.all_roles') }}</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected((string) request()->input('filter.role') === (string) $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <input type="text" name="filter[email]" class="form-control pull-right" value="{{ request()->input('filter.email') }}" placeholder="{{ __('admin/users.search') }}">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i></button>
                                @if(Auth::user()->hasAdminPermission('users.create'))
                                    <a href="{{ route('admin.users.new') }}"><button type="button" class="btn btn-sm btn-primary" style="border-radius: 0 3px 3px 0;margin-left:-1px;">{{ __('admin/common.actions.create_new') }}</button></a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('admin/users.id') }}</th>
                            <th>{{ __('admin/users.email') }}</th>
                            <th>{{ __('admin/users.client_name') }}</th>
                            <th>{{ __('admin/users.username') }}</th>
                            <th>{{ __('admin/roles.title') }}</th>
                            <th class="text-center">{{ __('admin/users.two_factor') }}</th>
                            <th class="text-center"><span data-toggle="tooltip" data-placement="top" title="{{ __('admin/users.servers_owned_help') }}">{{ __('admin/users.servers_owned') }}</span></th>
                            <th class="text-center"><span data-toggle="tooltip" data-placement="top" title="{{ __('admin/users.can_access_help') }}">{{ __('admin/users.can_access') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="align-middle">
                                <td><code>{{ $user->id }}</code></td>
                                <td><a href="{{ route('admin.users.view', $user->id) }}">{{ $user->email }}</a></td>
                                <td>{{ $user->name_last }}, {{ $user->name_first }}</td>
                                <td>{{ $user->username }}</td>
                                <td>@include('partials.hydra.role-badges', ['user' => $user])</td>
                                <td class="text-center">
                                    @if($user->use_totp)
                                        <i class="fa fa-lock text-green"></i>
                                    @else
                                        <i class="fa fa-unlock text-red"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.servers', ['filter[owner_id]' => $user->id]) }}">{{ $user->servers_count }}</a>
                                </td>
                                <td class="text-center">{{ $user->subuser_of_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $users->appends(['filter' => Request::input('filter')])->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
