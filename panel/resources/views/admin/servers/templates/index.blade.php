@extends('layouts.admin')

@section('title')
    {{ __('admin/server_templates.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/server_templates.title') }}<small>{{ __('admin/server_templates.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.servers') }}">{{ __('admin/servers.title') }}</a></li>
        <li class="active">{{ __('admin/server_templates.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/server_templates.list') }}</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.servers.templates.new') }}" class="btn btn-sm btn-primary">{{ __('admin/common.actions.create_new') }}</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>{{ __('admin/server_templates.name') }}</th>
                        <th>{{ __('admin/servers.fields.egg') }}</th>
                        <th>{{ __('admin/servers.fields.cpu') }}</th>
                        <th>{{ __('admin/servers.fields.memory') }}</th>
                        <th>{{ __('admin/servers.fields.disk') }}</th>
                        <th>{{ __('admin/server_templates.target') }}</th>
                        <th></th>
                    </tr>
                    @forelse($templates as $template)
                        <tr>
                            <td>
                                <a href="{{ route('admin.servers.templates.view', $template->id) }}">{{ $template->name }}</a>
                                @if($template->description)<br><span class="small text-muted">{{ $template->description }}</span>@endif
                            </td>
                            <td>{{ $template->egg->name }}</td>
                            <td>{{ $template->cpu ? $template->cpu . ' %' : __('admin/server_templates.unlimited') }}</td>
                            <td>{{ $template->memory ? $template->memory . ' MiB' : __('admin/server_templates.unlimited') }}</td>
                            <td>{{ $template->disk ? $template->disk . ' MiB' : __('admin/server_templates.unlimited') }}</td>
                            <td>{{ implode(', ', $template->targetNames()) ?: __('admin/server_templates.all_public') }}</td>
                            <td class="text-right" style="white-space: nowrap;">
                                @if(Auth::user()->hasAdminPermission('servers.create'))
                                    <a href="{{ route('admin.servers.new.template', ['template' => $template->id]) }}" class="btn btn-xs btn-primary">{{ __('admin/server_templates.deploy.short') }}</a>
                                @endif
                                <a href="{{ route('admin.servers.templates.view', $template->id) }}" class="btn btn-xs btn-default">{{ __('admin/common.actions.edit') }}</a>
                                <form action="{{ route('admin.servers.templates.view', $template->id) }}" method="POST" style="display: inline;" data-confirm="{{ __('admin/server_templates.delete_confirm', ['name' => $template->name]) }}">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">{{ __('admin/server_templates.none') }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('form[data-confirm]').on('submit', function () {
            return window.confirm($(this).data('confirm'));
        });
    </script>
@endsection
