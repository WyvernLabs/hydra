{{-- Hydra: field to pick a Lucide icon. Expects $name (input name) and $value. --}}
<div class="hydra-icon-field">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    <button type="button" class="btn btn-default hydra-icon-field-button" data-icon-picker @isset($disabled) @disabled($disabled) @endisset>
        <span class="hydra-icon-field-preview">{{ \Pterodactyl\Services\Hydra\Icons::svg($value, 'hydra-picker-icon') }}</span>
        <span class="hydra-icon-field-name">{{ $value }}</span>
    </button>
</div>
