{{-- Hydra: live resource usage of a node. Expects $node. Script: admin.nodes.partials.usage-script --}}
@php
    $gauges = [
        'cpu' => ['icon' => 'fa-microchip', 'title' => __('admin/nodes.usage.cpu')],
        'memory' => ['icon' => 'fa-server', 'title' => __('admin/nodes.usage.memory')],
        'disk' => ['icon' => 'fa-hdd-o', 'title' => __('admin/nodes.usage.disk')],
    ];
    $graphs = [
        'tx' => ['icon' => 'fa-arrow-up', 'title' => __('admin/nodes.usage.outgoing')],
        'rx' => ['icon' => 'fa-arrow-down', 'title' => __('admin/nodes.usage.incoming')],
    ];
@endphp
<div class="hydra-usage" id="nodeUsage" data-url="{{ route('admin.nodes.view.usage', $node->id) }}">
    <div class="hydra-usage-offline" data-usage-offline hidden>
        <i class="fa fa-plug"></i> {{ __('admin/nodes.usage.offline') }}
    </div>
    <div class="hydra-usage-grid">
        @foreach($gauges as $key => $gauge)
            <div class="hydra-usage-card" data-usage="{{ $key }}">
                <div class="hydra-usage-head">
                    <span class="hydra-usage-icon"><i class="fa {{ $gauge['icon'] }}"></i></span>
                    <span class="hydra-usage-title">{{ $gauge['title'] }}</span>
                    <span class="hydra-usage-limit" data-limit>—</span>
                </div>
                <div class="hydra-usage-ring">
                    <svg viewBox="0 0 100 100" aria-hidden="true">
                        <circle class="hydra-usage-ring-track" cx="50" cy="50" r="42"></circle>
                        <circle class="hydra-usage-ring-value" cx="50" cy="50" r="42" data-ring></circle>
                    </svg>
                    <div class="hydra-usage-ring-label">
                        <strong data-percent>—</strong>
                        <small>{{ __('admin/nodes.usage.of_limit') }}</small>
                    </div>
                </div>
                <div class="hydra-usage-value" data-value>—</div>
            </div>
        @endforeach
        @foreach($graphs as $key => $graph)
            <div class="hydra-usage-card" data-usage="{{ $key }}">
                <div class="hydra-usage-head">
                    <span class="hydra-usage-icon"><i class="fa {{ $graph['icon'] }}"></i></span>
                    <span class="hydra-usage-title">{{ $graph['title'] }}</span>
                    <span class="hydra-usage-limit" data-limit>—</span>
                </div>
                <div class="hydra-usage-graph">
                    <svg viewBox="0 0 120 60" preserveAspectRatio="none" aria-hidden="true">
                        <path class="hydra-usage-graph-area" data-area></path>
                        <path class="hydra-usage-graph-line" data-line></path>
                    </svg>
                    <span class="hydra-usage-graph-max" data-max></span>
                </div>
                <div class="hydra-usage-value">
                    <span data-value>—</span>
                    <small class="text-muted" data-percent></small>
                </div>
                <div class="hydra-usage-sub text-muted" data-total></div>
            </div>
        @endforeach
    </div>
    <div class="hydra-usage-facts">
        <div><span>{{ __('admin/nodes.usage.load') }}</span><strong data-fact="load">—</strong></div>
        <div><span>{{ __('admin/nodes.usage.uptime') }}</span><strong data-fact="uptime">—</strong></div>
        <div><span>{{ __('admin/nodes.usage.servers') }}</span><strong data-fact="servers">—</strong></div>
        <div><span>{{ __('admin/nodes.usage.swap') }}</span><strong data-fact="swap">—</strong></div>
    </div>
</div>
