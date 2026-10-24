@extends('layouts.admin')

@section('title')
    {{ __('admin/tickets.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/tickets.title') }}<small>{{ __('admin/tickets.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/tickets.title') }}</li>
    </ol>
@endsection

@section('content')
@php
    $tabs = [
        'active' => ($counts['open'] ?? 0) + ($counts['answered'] ?? 0),
        'open' => $counts['open'] ?? 0,
        'answered' => $counts['answered'] ?? 0,
        'closed' => $counts['closed'] ?? 0,
        'all' => $counts->sum(),
    ];
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                @foreach($tabs as $key => $count)
                    <li @class(['active' => $status === $key])>
                        <a href="{{ route('admin.tickets', array_filter(['status' => $key, 'assigned' => request('assigned'), 'language' => request('language'), 'search' => request('search')])) }}">
                            {{ __("admin/tickets.tabs.$key") }} <span class="badge">{{ $count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/tickets.list') }}</h3>
                <div class="box-tools search01">
                    <form action="{{ route('admin.tickets') }}" method="GET">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <div class="input-group input-group-sm">
                            <select name="assigned" class="form-control" onchange="this.form.submit()" style="width: auto; float: left;">
                                <option value="">{{ __('admin/tickets.assigned_any') }}</option>
                                <option value="me" @selected(request('assigned') === 'me')>{{ __('admin/tickets.assigned_me') }}</option>
                                <option value="none" @selected(request('assigned') === 'none')>{{ __('admin/tickets.assigned_none') }}</option>
                            </select>
                            @if(count($addon->languages()) > 1)
                                <select name="language" class="form-control" onchange="this.form.submit()" style="width: auto; float: left;">
                                    <option value="">{{ __('admin/tickets.language_any') }}</option>
                                    @foreach($addon->languages() as $code)
                                        <option value="{{ $code }}" @selected(request('language') === $code)>{{ \Pterodactyl\Services\Hydra\Addons\TicketsAddon::languageName($code) }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <input type="text" name="search" class="form-control pull-right" value="{{ request('search') }}" placeholder="{{ __('admin/tickets.search') }}">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-default"><x-hydra.icon name="search" /></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('admin/tickets.subject') }}</th>
                            <th>{{ __('admin/tickets.user') }}</th>
                            <th>{{ __('admin/tickets.server') }}</th>
                            <th>{{ __('admin/tickets.language') }}</th>
                            <th class="text-center">{{ __('admin/tickets.priority') }}</th>
                            <th class="text-center">{{ __('admin/tickets.status') }}</th>
                            <th>{{ __('admin/tickets.assignee') }}</th>
                            <th>{{ __('admin/tickets.last_reply') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr class="align-middle">
                                <td><code>{{ $ticket->id }}</code></td>
                                <td>
                                    <a href="{{ route('admin.tickets.view', $ticket->id) }}"><strong>{{ $ticket->subject }}</strong></a>
                                    @if($ticket->category)
                                        <div class="text-muted small">{{ $addon->categoryName($ticket->category, app()->getLocale()) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="hydra-user-cell">
                                        @include('partials.hydra.user-avatar', ['user' => $ticket->user, 'size' => 28])
                                        <span>{{ $ticket->user->username }}</span>
                                    </div>
                                </td>
                                <td>{{ $ticket->server?->name ?? '—' }}</td>
                                <td>{{ $ticket->language ? \Pterodactyl\Services\Hydra\Addons\TicketsAddon::languageName($ticket->language) : '—' }}</td>
                                <td class="text-center">@include('admin.tickets.partials.priority', ['priority' => $ticket->priority])</td>
                                <td class="text-center">@include('admin.tickets.partials.status', ['status' => $ticket->status])</td>
                                <td>{{ $ticket->assignee?->username ?? '—' }}</td>
                                <td>
                                    @if($ticket->last_reply_at)
                                        <span title="{{ $ticket->last_reply_at->locale(app()->getLocale())->isoFormat('LLL') }}">{{ $ticket->last_reply_at->locale(app()->getLocale())->diffForHumans() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">{{ __('admin/tickets.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($tickets->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $tickets->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
