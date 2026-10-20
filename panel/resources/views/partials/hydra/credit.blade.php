{{-- Hydra: mandatory footer credit, see BrandingService::CREDIT. --}}
@php($hydraName = trim((string) config('app.name', 'Hydra')))
@if(strcasecmp($hydraName, 'Hydra') === 0)
    {{ \Pterodactyl\Services\Hydra\BrandingService::CREDIT }} &copy; {{ date('Y') }}
@else
    {{ $hydraName }} &copy; {{ date('Y') }} &middot; {{ \Pterodactyl\Services\Hydra\BrandingService::CREDIT }}
@endif
