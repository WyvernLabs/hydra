{{-- Hydra: the public status page (addon "status"). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1" name="viewport">
        <meta http-equiv="refresh" content="60">
        <title>{{ $title }} – {{ config('app.name', 'Hydra') }}</title>
        @include('partials.hydra.head')
    </head>
    <body class="hydra-body">
        <main class="hydra-public-page hydra-status">
            <a href="{{ route('auth.login') }}" class="hydra-public-page-logo">
                <img src="{{ $branding->logoUrl() }}" alt="{{ config('app.name', 'Hydra') }}">
            </a>

            <div class="hydra-status-overall" data-state="{{ $overall }}">
                <h1>{{ $title }}</h1>
                <p class="hydra-status-overall-state">{{ __('client/status.overall.' . $overall) }}</p>
                @if($description)
                    <p class="hydra-status-description">{{ $description }}</p>
                @endif
            </div>

            @foreach(['nodes' => $nodes, 'services' => $services] as $group => $entries)
                @if(!empty($entries))
                    <section class="hydra-box hydra-status-group">
                        <h2 class="hydra-status-group-title">{{ __('client/status.groups.' . $group) }}</h2>
                        @foreach($entries as $entry)
                            <div class="hydra-status-entry">
                                <div class="hydra-status-entry-head">
                                    <strong>{{ $entry['name'] }}</strong>
                                    <span class="hydra-status-state" data-state="{{ $entry['state'] }}">{{ __('client/status.states.' . $entry['state']) }}</span>
                                </div>
                                <div class="hydra-status-bars" role="img" aria-label="{{ $entry['uptime'] === null ? __('client/status.no_data') : __('client/status.uptime', ['percent' => $entry['uptime']]) }}">
                                    @foreach($entry['bars'] as $bar)
                                        <span data-state="{{ $bar['state'] }}" title="{{ $bar['hour']->format(__('client/status.hour_format')) }} – {{ $bar['uptime'] === null ? __('client/status.no_data') : __('client/status.uptime', ['percent' => $bar['uptime']]) }}"></span>
                                    @endforeach
                                </div>
                                <div class="hydra-status-scale">
                                    <span>{{ __('client/status.seven_days_ago') }}</span>
                                    <span>{{ $entry['uptime'] === null ? __('client/status.no_data') : __('client/status.uptime', ['percent' => $entry['uptime']]) }}</span>
                                    <span>{{ __('client/status.now') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif
            @endforeach

            <div class="hydra-status-legend">
                @foreach(['online', 'partial', 'offline', 'unknown'] as $state)
                    <span><i data-state="{{ $state }}"></i>{{ __('client/status.legend.' . $state) }}</span>
                @endforeach
            </div>

            <p class="hydra-footer">
                @include('partials.hydra.credit')
                &middot; <a href="{{ route('auth.login') }}">{{ __('client/auth.login.title') }}</a>
            </p>
        </main>
    </body>
</html>
