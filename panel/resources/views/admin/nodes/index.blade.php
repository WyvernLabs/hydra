@extends('layouts.admin')

@section('title')
    {{ __('admin/nodes.list_title') }}
@endsection

@section('scripts')
    @parent
    {!! Theme::css('vendor/fontawesome/animation.min.css') !!}
@endsection

@section('content-header')
    <h1>{{ __('admin/nodes.title') }}<small>{{ __('admin/nodes.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/nodes.title') }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/nodes.list') }}</h3>
                <div class="box-tools search01">
                    <form action="{{ route('admin.nodes') }}" method="GET">
                        <div class="input-group input-group-sm">
                            <input type="text" name="filter[name]" class="form-control pull-right" value="{{ request()->input('filter.name') }}" placeholder="{{ __('admin/nodes.search') }}">
                            <div class="input-group-btn">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i></button>
                                @if(Auth::user()->hasAdminPermission('nodes.create'))<a href="{{ route('admin.nodes.new') }}"><button type="button" class="btn btn-sm btn-primary" style="border-radius: 0 3px 3px 0;margin-left:-1px;">{{ __('admin/common.actions.create_new') }}</button></a>@endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th></th>
                            <th>{{ __('admin/nodes.name') }}</th>
                            <th>{{ __('admin/nodes.location') }}</th>
                            <th>{{ __('admin/nodes.memory') }}</th>
                            <th>{{ __('admin/nodes.disk') }}</th>
                            <th>{{ __('admin/nodes.usage.live') }}</th>
                            <th class="text-center">{{ __('admin/nodes.servers') }}</th>
                            <th class="text-center">{{ __('admin/nodes.ssl') }}</th>
                            <th class="text-center">{{ __('admin/nodes.public') }}</th>
                        </tr>
                        @foreach ($nodes as $node)
                            <tr>
                                <td class="text-center text-muted left-icon" data-action="ping" data-url="{{ route('admin.nodes.view.usage', $node->id) }}"><i class="fa fa-fw fa-refresh fa-spin"></i></td>
                                <td>{!! $node->maintenance_mode ? '<span class="label label-warning"><i class="fa fa-wrench"></i></span> ' : '' !!}<a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></td>
                                <td>{{ $node->location->short }}</td>
                                <td>{{ $node->memory }} MiB</td>
                                <td>{{ $node->disk }} MiB</td>
                                <td>
                                    <div class="hydra-mini-usage" data-mini-usage>
                                        @foreach(['cpu' => 'CPU', 'memory' => 'RAM', 'disk' => 'Disk'] as $key => $label)
                                            <div><span>{{ $label }}</span><div class="hydra-mini-bar"><i data-bar="{{ $key }}"></i></div><em data-text="{{ $key }}">—</em></div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-center">{{ $node->servers_count }}</td>
                                <td class="text-center" style="color:{{ ($node->scheme === 'https') ? '#50af51' : '#d9534f' }}"><i class="fa fa-{{ ($node->scheme === 'https') ? 'lock' : 'unlock' }}"></i></td>
                                <td class="text-center"><i class="fa fa-{{ ($node->public) ? 'eye' : 'eye-slash' }}"></i></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($nodes->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $nodes->appends(['query' => Request::input('query')])->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    (function pingNodes() {
        function level(percent) {
            return percent >= 90 ? 'danger' : percent >= 75 ? 'warning' : 'ok';
        }

        function bar(row, key, percent) {
            percent = Math.max(0, Math.min(100, percent || 0));
            row.find('[data-bar="' + key + '"]').css('width', percent + '%').attr('data-level', level(percent));
            row.find('[data-text="' + key + '"]').text(Math.round(percent) + '%');
        }

        $('td[data-action="ping"]').each(function (i, element) {
            var icon = $(element).find('i');
            var row = $(element).closest('tr');
            $.ajax({ type: 'GET', url: $(element).data('url'), dataType: 'json', timeout: 8000 }).done(function (data) {
                if (!data.online) {
                    icon.removeClass().addClass('fa fa-fw fa-heart-o').css('color', '#d9534f');
                    icon.attr('data-original-title', @json(__('admin/nodes.ping_error'))).tooltip();
                    return;
                }
                $(element).removeClass('text-muted');
                icon.removeClass().addClass('fa fa-fw fa-heartbeat faa-pulse animated').css('color', '#50af51');
                bar(row, 'cpu', data.cpu);
                bar(row, 'memory', data.memory_total ? data.memory_used / data.memory_total * 100 : 0);
                bar(row, 'disk', data.disk_total ? data.disk_used / data.disk_total * 100 : 0);
            }).fail(function () {
                icon.removeClass().addClass('fa fa-fw fa-heart-o').css('color', '#d9534f');
            });
        }).promise().done(function () {
            setTimeout(pingNodes, 10000);
        });
    })();
    </script>
@endsection
