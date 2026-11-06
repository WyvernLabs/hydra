{{-- Hydra: seasonal effects (Design > Effects). Nothing is loaded while no event runs. --}}
@php($hydraEffects = app(\Pterodactyl\Services\Hydra\Effects\SeasonalEffects::class)->forPage(Auth::user()))
@if($hydraEffects)
    <link rel="stylesheet" href="/hydra/effects.css?v={{ substr(md5_file(public_path('hydra/effects.css')), 0, 8) }}">
    <script>
        window.HydraEffectsConfig = @json($hydraEffects);
        document.documentElement.setAttribute('data-hydra-effect', window.HydraEffectsConfig.event);
        document.documentElement.setAttribute('data-hydra-decor', window.HydraEffectsConfig.values.decor ? 'on' : 'off');
    </script>
    <script src="/hydra/effects.js?v={{ substr(md5_file(public_path('hydra/effects.js')), 0, 8) }}" defer></script>
@endif
