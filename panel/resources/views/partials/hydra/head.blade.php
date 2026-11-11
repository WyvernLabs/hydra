{{-- Shared by the client area (templates/wrapper) and the admin area (layouts/admin). --}}
@php($pwa = app(\Pterodactyl\Services\Hydra\Addons\PwaAddon::class))
@php($metaColor = app(\Pterodactyl\Services\Hydra\MetadataService::class)->values()['color'])
@include('partials.hydra.meta')
<link rel="icon" href="{{ $branding->faviconUrl() }}">
@if($pwa->enabled())
    {{-- Hydra: installable app (addon "pwa"). --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/pwa/icon-192.png?v={{ app(\Pterodactyl\Services\Hydra\PwaService::class)->version() }}">
    <meta name="theme-color" content="{{ $metaColor ?? $pwa->themeColor() }}">
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
    <meta name="theme-color" content="{{ $metaColor ?? $branding->accent() }}">
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
<style id="hydra-branding">{!! $branding->cssVariables() !!}{!! app(\Pterodactyl\Services\Hydra\SurfaceService::class)->cssVariables() !!}</style>
{{-- Hydra: live preview of the designer (public/hydra/designer.js). Only inside its frame and only from the same origin. --}}
<script>
    (function () {
        if (window.self === window.top || !/[?&]hydra_designer=1(&|$)/.test(window.location.search)) return;
        document.documentElement.classList.add('hydra-designer-frame');
        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin || !event.data || event.data.type !== 'hydra-designer') return;
            var root = document.documentElement;
            (window.hydraDesignerVars || []).forEach(function (name) { root.style.removeProperty(name); });
            window.hydraDesignerVars = Object.keys(event.data.vars || {});
            window.hydraDesignerVars.forEach(function (name) { root.style.setProperty(name, event.data.vars[name]); });
            window.hydraDesigner = event.data.state || {};
            window.dispatchEvent(new CustomEvent('hydra-designer', { detail: window.hydraDesigner }));
        });
        window.parent.postMessage({ type: 'hydra-designer-ready' }, window.location.origin);
    })();
</script>
@include('partials.hydra.effects')
