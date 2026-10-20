@props(['name', 'class' => 'hydra-nav-icon', 'style' => null])
{{-- Hydra: a Lucide icon by name, the same icons the React client area uses. --}}
{{ \Pterodactyl\Services\Hydra\Icons::svg($name, trim($class . ' ' . ($attributes->get('class') ?? '')), $style) }}
