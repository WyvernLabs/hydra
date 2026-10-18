{{-- Shared by the client area (templates/wrapper) and the admin area (layouts/admin). --}}
<link rel="icon" href="{{ $branding->faviconUrl() }}">
<link rel="apple-touch-icon" href="{{ $branding->faviconUrl() }}">
<meta name="theme-color" content="{{ $branding->accent() }}">
<link rel="stylesheet" href="/hydra/tokens.css?v={{ config('app.version') }}">
<style id="hydra-branding">{!! $branding->cssVariables() !!}</style>
