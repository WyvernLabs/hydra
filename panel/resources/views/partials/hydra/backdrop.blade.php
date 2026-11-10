{{--
    Hydra: a page background set under Design > Backgrounds, drawn behind
    everything (like HydraBackdrop.tsx in the client area). Expects
    $background (image, blur, dim); nothing changed = the body background.
--}}
@if($background['image'] || $background['blur'] || $background['dim'])
    <div class="hydra-backdrop-layer" aria-hidden="true">
        <div class="hydra-backdrop-layer-image" style="@if($background['image'])background-image: url('{{ $background['image'] }}'); background-size: cover; background-position: center; background-repeat: no-repeat;@endif @if($background['blur'])filter: blur({{ (int) $background['blur'] }}px);@endif"></div>
        @if($background['dim'] > 0)
            <div class="hydra-backdrop-layer-dim" style="opacity: {{ $background['dim'] / 100 }};"></div>
        @endif
    </div>
@endif
