@extends('layouts.admin')

@section('title')
    {{ __('admin/tickets.view_title', ['id' => $ticket->id]) }}
@endsection

@section('content-header')
    <h1>{{ $ticket->subject }}<small>#{{ $ticket->id }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.tickets') }}">{{ __('admin/tickets.title') }}</a></li>
        <li class="active">#{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
@php($admin = Auth::user())
<div class="row">
    <div class="col-md-8">
        @foreach($ticket->messages as $message)
            <div @class(['box', 'hydra-ticket-message', 'is-staff' => $message->staff && !$message->internal, 'is-internal' => $message->internal])>
                <div class="box-header with-border hydra-ticket-message-header">
                    @if($message->user)
                        @include('partials.hydra.user-avatar', ['user' => $message->user, 'size' => 32])
                    @endif
                    <div>
                        <strong>{{ $message->user?->username ?? __('admin/tickets.deleted_user') }}</strong>
                        @if($message->internal)
                            <span class="label label-warning">{{ __('admin/tickets.internal_note') }}</span>
                        @elseif($message->staff)
                            <span class="label label-primary">{{ __('admin/tickets.team') }}</span>
                        @endif
                        <div class="text-muted small">{{ $message->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</div>
                    </div>
                </div>
                <div class="box-body hydra-markdown">{!! $html[$message->id] !!}</div>
            </div>
        @endforeach

        @if($admin->hasAdminPermission('tickets.reply'))
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/tickets.reply') }}</h3>
                </div>
                <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    <div class="box-body">
                        @include('admin.tickets.partials.editor', ['name' => 'body', 'value' => old('body')])
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="ticketInternal" name="internal" value="1" @checked(old('internal'))>
                            <label for="ticketInternal">{{ __('admin/tickets.internal') }}</label>
                        </div>
                        <p class="text-muted small no-margin">{{ __('admin/tickets.internal_help') }}</p>
                        @if($admin->hasAdminPermission('tickets.manage') && !$ticket->isClosed())
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="ticketClose" name="close" value="1">
                                <label for="ticketClose">{{ __('admin/tickets.close_after') }}</label>
                            </div>
                        @endif
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary pull-right">{{ __('admin/tickets.send') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <div class="col-md-4">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/tickets.details') }}</h3>
            </div>
            <div class="box-body">
                <table class="table table-condensed no-margin">
                    <tr>
                        <th>{{ __('admin/tickets.user') }}</th>
                        <td>
                            @if($admin->hasAdminPermission('users.view'))
                                <a href="{{ route('admin.users.view', $ticket->user_id) }}">{{ $ticket->user->username }}</a>
                            @else
                                {{ $ticket->user->username }}
                            @endif
                            <div class="text-muted small">{{ $ticket->user->email }}</div>
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/tickets.server') }}</th>
                        <td>
                            @if($ticket->server && $admin->hasAdminPermission('servers.view'))
                                <a href="{{ route('admin.servers.view', $ticket->server_id) }}">{{ $ticket->server->name }}</a>
                            @else
                                {{ $ticket->server?->name ?? '—' }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/tickets.category') }}</th>
                        <td>{{ $addon->categoryName($ticket->category, app()->getLocale()) ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/tickets.language') }}</th>
                        <td>{{ $ticket->language ? \Pterodactyl\Services\Hydra\Addons\TicketsAddon::languageName($ticket->language) : '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/tickets.created') }}</th>
                        <td>{{ $ticket->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/tickets.status') }}</th>
                        <td>@include('admin.tickets.partials.status', ['status' => $ticket->status])</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/tickets.priority') }}</th>
                        <td>@include('admin.tickets.partials.priority', ['priority' => $ticket->priority])</td>
                    </tr>
                    <tr>
                        <th>{{ __('admin/tickets.assignee') }}</th>
                        <td>{{ $ticket->assignee?->username ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($admin->hasAdminPermission('tickets.manage'))
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/tickets.manage') }}</h3>
                </div>
                <form action="{{ route('admin.tickets.update', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <div class="box-body">
                        <div class="form-group">
                            <label for="ticketStatus" class="control-label">{{ __('admin/tickets.status') }}</label>
                            <select id="ticketStatus" name="status" class="form-control">
                                @foreach(\Pterodactyl\Models\Ticket::STATUSES as $value)
                                    <option value="{{ $value }}" @selected($ticket->status === $value)>{{ __("admin/tickets.statuses.$value") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticketPriority" class="control-label">{{ __('admin/tickets.priority') }}</label>
                            <select id="ticketPriority" name="priority" class="form-control">
                                @foreach(\Pterodactyl\Models\Ticket::PRIORITIES as $value)
                                    <option value="{{ $value }}" @selected($ticket->priority === $value)>{{ __("admin/tickets.priorities.$value") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group no-margin">
                            <label for="ticketAssignee" class="control-label">{{ __('admin/tickets.assignee') }}</label>
                            <select id="ticketAssignee" name="assigned_to" class="form-control">
                                <option value="">{{ __('admin/tickets.nobody') }}</option>
                                @foreach($team as $member)
                                    <option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>{{ $member->username }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    </div>
                </form>
            </div>
        @endif

        @if($otherTickets->isNotEmpty())
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/tickets.other_tickets') }}</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-condensed no-margin">
                        @foreach($otherTickets as $other)
                            <tr>
                                <td><a href="{{ route('admin.tickets.view', $other->id) }}">#{{ $other->id }} {{ $other->subject }}</a></td>
                                <td class="text-right">@include('admin.tickets.partials.status', ['status' => $other->status])</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif

        @if($admin->hasAdminPermission('tickets.delete'))
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/tickets.delete') }}</h3>
                </div>
                <div class="box-body">
                    <p class="no-margin">{{ __('admin/tickets.delete_text') }}</p>
                </div>
                <div class="box-footer">
                    <form action="{{ route('admin.tickets.delete', $ticket->id) }}" method="POST" onsubmit="return confirm(@js(__('admin/tickets.delete_confirm')));">
                        {!! csrf_field() !!}
                        {!! method_field('DELETE') !!}
                        <button type="submit" class="btn btn-sm btn-danger pull-right">{{ __('admin/tickets.delete') }}</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script src="/hydra/ticket-editor.js?v={{ substr(md5_file(public_path('hydra/ticket-editor.js')), 0, 8) }}"></script>
@endsection
