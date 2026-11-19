{{--
    Hydra: copyright line of the footers. Pages for users show the text from
    Designer > Metadata (FooterService) or "Name © year". Only the admin area
    (['admin' => true]) shows the fixed credit BrandingService::CREDIT.
--}}
@php($hydraName = trim((string) config('app.name', 'Hydra')))
@if(!empty($admin))
    @if(strcasecmp($hydraName, 'Hydra') === 0)
        {{ \Pterodactyl\Services\Hydra\BrandingService::CREDIT }} &copy; {{ date('Y') }}
    @else
        {{ $hydraName }} &copy; {{ date('Y') }} &middot; {{ \Pterodactyl\Services\Hydra\BrandingService::CREDIT }}
    @endif
@else
    {{ app(\Pterodactyl\Services\Hydra\FooterService::class)->text(app()->getLocale()) ?? $hydraName . ' © ' . date('Y') }}
@endif
