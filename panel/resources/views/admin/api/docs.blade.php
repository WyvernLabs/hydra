@extends('layouts.admin')

@section('title')
    {{ __('admin/api_docs.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/api_docs.title') }}<small>{{ __('admin/api_docs.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.api.index') }}">{{ __('admin/api.title') }}</a></li>
        <li class="active">{{ __('admin/api_docs.tabs.docs') }}</li>
    </ol>
@endsection

@php
    $methodClass = ['GET' => 'label-success', 'POST' => 'label-primary', 'PUT' => 'label-warning', 'PATCH' => 'label-warning', 'DELETE' => 'label-danger'];
@endphp

@section('content')
@include('partials.admin.api.tabs', ['active' => 'docs'])
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs" id="apiDocTabs">
                <li class="active"><a href="#api-intro" data-toggle="tab">{{ __('admin/api_docs.sections.intro') }}</a></li>
                @foreach($apis as $api => $groups)
                    <li><a href="#api-{{ $api }}" data-toggle="tab">{{ __('admin/api_docs.sections.' . $api) }}
                        <span class="badge" style="margin-left: 4px;">{{ collect($groups)->flatten(1)->count() }}</span></a></li>
                @endforeach
            </ul>
            <div class="tab-content">
                <div class="tab-pane active" id="api-intro">
                    <h4>{{ __('admin/api_docs.intro.apis_title') }}</h4>
                    <p>{{ __('admin/api_docs.intro.apis') }}</p>
                    <div class="row">
                        <div class="col-md-6">
                            <h5><strong>{{ __('admin/api_docs.intro.application_title') }}</strong> <code>{{ $url }}/api/application</code></h5>
                            <p class="text-muted">{{ __('admin/api_docs.intro.application') }}</p>
                        </div>
                        <div class="col-md-6">
                            <h5><strong>{{ __('admin/api_docs.intro.client_title') }}</strong> <code>{{ $url }}/api/client</code></h5>
                            <p class="text-muted">{{ __('admin/api_docs.intro.client') }}</p>
                        </div>
                    </div>
                    <h4>{{ __('admin/api_docs.intro.auth_title') }}</h4>
                    <p>{{ __('admin/api_docs.intro.auth') }}</p>
                    <pre class="hydra-update-log">Authorization: Bearer ptlc_…
Accept: Application/vnd.pterodactyl.v1+json
Content-Type: application/json</pre>
                    <h4>{{ __('admin/api_docs.intro.example_title') }}</h4>
                    <p>{{ __('admin/api_docs.intro.example') }}</p>
                    <pre class="hydra-update-log">curl -s {{ $url }}/api/client \
  -H "Authorization: Bearer ptlc_…" \
  -H "Accept: Application/vnd.pterodactyl.v1+json"</pre>
                    <h4>{{ __('admin/api_docs.intro.pagination_title') }}</h4>
                    <p>{{ __('admin/api_docs.intro.pagination') }}</p>
                    <h4>{{ __('admin/api_docs.intro.errors_title') }}</h4>
                    <p>{{ __('admin/api_docs.intro.errors') }}</p>
                    <h4>{{ __('admin/api_docs.intro.limits_title') }}</h4>
                    <p>{{ __('admin/api_docs.intro.limits', [
                        'client' => $limits['client'] ?? 256, 'client_period' => $limits['client_period'] ?? 1,
                        'application' => $limits['application'] ?? 256, 'application_period' => $limits['application_period'] ?? 1,
                    ]) }}</p>
                    <h4>{{ __('admin/api_docs.intro.websocket_title') }}</h4>
                    <p class="no-margin">{{ __('admin/api_docs.intro.websocket') }}</p>
                </div>
                @foreach($apis as $api => $groups)
                    <div class="tab-pane" id="api-{{ $api }}" data-api>
                        <div class="form-group">
                            <input type="search" class="form-control" placeholder="{{ __('admin/api_docs.search') }}" data-search>
                        </div>
                        <p class="text-muted" data-empty hidden>{{ __('admin/api_docs.no_results') }}</p>
                        @foreach($groups as $group => $endpoints)
                            <div class="box box-default" data-group>
                                <div class="box-header with-border">
                                    <h3 class="box-title">{{ __("admin/api_docs.groups.$api.$group.title") }}</h3>
                                    <span class="text-muted small" style="margin-left: 8px;">{{ __("admin/api_docs.groups.$api.$group.description") }}</span>
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-hover" style="margin: 0;">
                                        <tbody>
                                            @foreach($endpoints as $endpoint)
                                                <tr data-endpoint data-text="{{ strtolower($endpoint['method'] . ' ' . $endpoint['path'] . ' ' . $endpoint['description'] . ' ' . $endpoint['permission']) }}">
                                                    <td style="width: 70px; vertical-align: top;">
                                                        <span class="label {{ $methodClass[$endpoint['method']] ?? 'label-default' }}" style="display: inline-block; min-width: 54px;">{{ $endpoint['method'] }}</span>
                                                    </td>
                                                    <td style="vertical-align: top;">
                                                        <code style="white-space: nowrap;">{{ $endpoint['path'] }}</code>
                                                        <div class="small" style="margin-top: 4px;">{{ $endpoint['description'] ?? __('admin/api_docs.no_description') }}</div>
                                                        @if(!empty($endpoint['fields']))
                                                            <details class="small" style="margin-top: 4px;">
                                                                <summary class="text-muted" style="cursor: pointer;">{{ __('admin/api_docs.fields') }} ({{ count($endpoint['fields']) }})</summary>
                                                                <table class="table table-condensed" style="margin: 4px 0 0; background: transparent;">
                                                                    @foreach($endpoint['fields'] as $field => $rules)
                                                                        <tr><td style="width: 30%;"><code>{{ $field }}</code></td><td class="text-muted">{{ $rules }}</td></tr>
                                                                    @endforeach
                                                                </table>
                                                            </details>
                                                        @endif
                                                    </td>
                                                    <td style="width: 200px; vertical-align: top; text-align: right;">
                                                        @if($endpoint['permission'])
                                                            <span class="label label-default" title="{{ __('admin/api_docs.permission') }}">{{ $endpoint['permission'] }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            // Opens the tab of the hash (#api-client), so links can point to a section.
            if (location.hash && $('#apiDocTabs a[href="' + location.hash + '"]').length) {
                $('#apiDocTabs a[href="' + location.hash + '"]').tab('show');
            }
            $('#apiDocTabs a').on('shown.bs.tab', function (event) {
                history.replaceState(null, '', $(event.target).attr('href'));
            });

            $('[data-api] [data-search]').on('input', function () {
                var $pane = $(this).closest('[data-api]');
                var words = $(this).val().toLowerCase().split(/\s+/).filter(Boolean);
                var found = 0;
                $pane.find('[data-group]').each(function () {
                    var visible = 0;
                    $(this).find('[data-endpoint]').each(function () {
                        var text = $(this).data('text');
                        var show = words.every(function (word) { return text.indexOf(word) !== -1; });
                        $(this).prop('hidden', !show);
                        if (show) visible++;
                    });
                    $(this).prop('hidden', visible === 0);
                    found += visible;
                });
                $pane.find('[data-empty]').prop('hidden', found > 0);
            });
        })();
    </script>
@endsection
