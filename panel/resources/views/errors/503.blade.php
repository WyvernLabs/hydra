{{--
    Hydra: "503 Service Unavailable" (Design > Maintenance page). Shown while
    the panel updates itself and for any other 503.

    During an update this page is rendered once into storage/framework/down
    and printed without starting the framework, so everything it needs is
    inline or a static file (see MaintenancePageService). Optional variables:
    $hydraPreview (admin preview, no auto reload) and $hydraLocale.
--}}
@php
    try {
        $page = app(\Pterodactyl\Services\Hydra\MaintenancePageService::class)->pageData($hydraLocale ?? null);
    } catch (\Throwable $exception) {
        $page = \Pterodactyl\Services\Hydra\MaintenancePageService::fallback();
    }
    $background = $page['background'];
@endphp
<!DOCTYPE html>
<html lang="{{ $page['locale'] }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $page['text']['title'] }} – {{ $page['name'] }}</title>
        <link rel="icon" href="{{ $page['favicon'] }}">
        <style>
            :root {
                --hydra-accent-500: 116 194 54;
                --hydra-surface-800: 31 31 31;
                --hydra-surface-alpha: 1;
                --hydra-line: 46 46 46;
                --hydra-line-alpha: 1;
                --hydra-backdrop: none;
            }
            {!! $page['css'] !!}
            *, *::before, *::after { box-sizing: border-box; }
            html, body { margin: 0; min-height: 100%; }
            body {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 28px;
                padding: 32px 16px;
                color: rgb(236 236 236);
                background: rgb(20 20 20) url("/hydra/hex-dark.svg");
                font-family: "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            .hydra-503-bg { position: fixed; inset: 0; z-index: -2; overflow: hidden; }
            .hydra-503-bg-image {
                position: absolute;
                inset: calc(var(--blur, 0px) * -2);
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                filter: blur(var(--blur, 0px));
            }
            .hydra-503-dim { position: fixed; inset: 0; z-index: -1; background: #000; }
            .hydra-503-box {
                width: 100%;
                max-width: 480px;
                padding: 40px 32px 32px;
                text-align: center;
                border-radius: 18px;
                background: rgb(var(--hydra-surface-800) / var(--hydra-surface-alpha));
                border: 1px solid rgb(var(--hydra-line) / var(--hydra-line-alpha));
                -webkit-backdrop-filter: var(--hydra-backdrop);
                backdrop-filter: var(--hydra-backdrop);
                box-shadow: 0 24px 60px rgb(0 0 0 / 0.35);
                animation: hydra-503-float 6s ease-in-out infinite;
                will-change: transform;
            }
            @keyframes hydra-503-float {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            .hydra-503-logo { display: block; max-width: 220px; max-height: 64px; margin: 0 auto 26px; }
            .hydra-503-logo.is-protected { pointer-events: none; user-select: none; -webkit-user-drag: none; -webkit-touch-callout: none; }
            .hydra-503-box h1 { margin: 0 0 12px; font-size: 1.55rem; font-weight: 600; line-height: 1.3; }
            .hydra-503-description { margin: 0; color: rgb(196 196 196); line-height: 1.65; white-space: pre-line; overflow-wrap: anywhere; }
            .hydra-503-status {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                margin-top: 28px;
                padding: 8px 14px;
                border-radius: 999px;
                font-size: 0.82rem;
                color: rgb(170 170 170);
                background: rgb(var(--hydra-accent-500) / 0.08);
                border: 1px solid rgb(var(--hydra-accent-500) / 0.2);
            }
            .hydra-503-dots { display: inline-flex; gap: 5px; }
            .hydra-503-dots span {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: rgb(var(--hydra-accent-500));
                animation: hydra-503-dot 1.4s ease-in-out infinite both;
            }
            .hydra-503-dots span:nth-child(2) { animation-delay: 0.16s; }
            .hydra-503-dots span:nth-child(3) { animation-delay: 0.32s; }
            @keyframes hydra-503-dot {
                0%, 80%, 100% { opacity: 0.25; transform: scale(0.75); }
                40% { opacity: 1; transform: scale(1); }
            }
            .hydra-503-footer { font-size: 0.75rem; color: rgb(141 141 141); text-align: center; }
            @media (max-width: 480px) {
                .hydra-503-box { padding: 32px 20px 24px; }
                .hydra-503-box h1 { font-size: 1.3rem; }
            }
            @media (prefers-reduced-motion: reduce) {
                .hydra-503-box, .hydra-503-dots span { animation: none; }
            }
        </style>
    </head>
    <body>
        @if($background['image'])
            <div class="hydra-503-bg" aria-hidden="true">
                <div class="hydra-503-bg-image" style="background-image: url('{{ $background['image'] }}'); --blur: {{ (int) $background['blur'] }}px;"></div>
            </div>
        @endif
        @if($background['dim'] > 0)
            <div class="hydra-503-dim" style="opacity: {{ $background['dim'] / 100 }};" aria-hidden="true"></div>
        @endif

        <main class="hydra-503-box">
            @if($page['logo'])
                <img src="{{ $page['logo'] }}" alt="{{ $page['name'] }}" class="hydra-503-logo @if($page['protect']) is-protected @endif" draggable="false">
            @endif
            <h1 data-hydra-text="title">{{ $page['text']['title'] }}</h1>
            <p class="hydra-503-description" data-hydra-text="description">{{ $page['text']['description'] }}</p>
            <div class="hydra-503-status" role="status">
                <span class="hydra-503-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                <span data-hydra-text="status">{{ $page['text']['status'] }}</span>
            </div>
        </main>

        <footer class="hydra-503-footer">@include('partials.hydra.credit')</footer>

        <script>
            (function () {
                var texts = @json($page['texts']);
                var current = @json($page['locale']);
                var forced = @json(isset($hydraLocale) ? $hydraLocale : null);

                // The page is rendered once for all visitors, so pick the language in the browser.
                var wanted = forced ? [forced] : (navigator.languages || [navigator.language || '']);
                for (var i = 0; i < wanted.length; i++) {
                    var code = String(wanted[i] || '').toLowerCase().split('-')[0];
                    if (texts[code]) { current = code; break; }
                }
                var text = texts[current];
                if (text) {
                    document.documentElement.lang = current;
                    document.title = text.title + ' – ' + @json($page['name']);
                    ['title', 'description', 'status'].forEach(function (key) {
                        var node = document.querySelector('[data-hydra-text="' + key + '"]');
                        if (node && text[key]) node.textContent = text[key];
                    });
                }

                @if(empty($hydraPreview))
                    // Reload as soon as this address answers normally again.
                    setInterval(function () {
                        if (!window.fetch) return;
                        fetch(window.location.href, { cache: 'no-store', credentials: 'same-origin', redirect: 'follow' })
                            .then(function (response) { if (response.status < 500) window.location.reload(); })
                            .catch(function () {});
                    }, 15000);
                @endif
            })();
        </script>
    </body>
</html>
