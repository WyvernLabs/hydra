{{-- Shared by the client area (templates/wrapper) and the admin area (layouts/admin). --}}
@php($pwa = app(\Pterodactyl\Services\Hydra\Addons\PwaAddon::class))
<link rel="icon" href="{{ $branding->faviconUrl() }}">
@if($pwa->enabled())
    {{-- Hydra: installable app (addon "pwa"). --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/pwa/icon-192.png?v={{ app(\Pterodactyl\Services\Hydra\PwaService::class)->version() }}">
    <meta name="theme-color" content="{{ $pwa->themeColor() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
            });
        }
    </script>
@else
    <link rel="apple-touch-icon" href="{{ $branding->faviconUrl() }}">
    <meta name="theme-color" content="{{ $branding->accent() }}">
    <script>
        // The app was switched off: its service worker goes too.
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function (list) {
                list.forEach(function (registration) { registration.unregister(); });
            }).catch(function () {});
        }
    </script>
@endif
<link rel="stylesheet" href="/hydra/tokens.css?v={{ substr(md5_file(public_path('hydra/tokens.css')), 0, 8) }}">
<link rel="stylesheet" href="/hydra/layout.css?v={{ substr(md5_file(public_path('hydra/layout.css')), 0, 8) }}">
<style id="hydra-branding">{!! $branding->cssVariables() !!}</style>
