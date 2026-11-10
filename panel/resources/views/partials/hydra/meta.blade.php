{{-- Hydra: description, link preview (Open Graph, X) and search engines. Design > Metadata. --}}
{{-- The admin area has its own noindex and needs no link preview. --}}
@unless(request()->is('admin', 'admin/*'))
@php($meta = app(\Pterodactyl\Services\Hydra\MetadataService::class)->tags())
@if(!$meta['indexable'])
    <meta name="robots" content="noindex">
@endif
@if($meta['description'] !== '')
    <meta name="description" content="{{ $meta['description'] }}">
@endif
@if($meta['keywords'] !== '')
    <meta name="keywords" content="{{ $meta['keywords'] }}">
@endif
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $meta['site_name'] }}">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
@if($meta['description'] !== '')
    <meta property="og:description" content="{{ $meta['description'] }}">
@endif
@if($meta['image'])
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:image" content="{{ $meta['image'] }}">
@endif
<meta name="twitter:card" content="{{ $meta['card'] }}">
<meta name="twitter:title" content="{{ $meta['title'] }}">
@if($meta['description'] !== '')
    <meta name="twitter:description" content="{{ $meta['description'] }}">
@endif
@endunless
