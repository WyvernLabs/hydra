{{--
    Hydra: one loading animation (Designer > Loading animation). Same markup as
    components/elements/Spinner.tsx; drawn by ".hydra-loader" in layout.css.
    Expects $style, optional $gif (URL) and $size (small|base|large).
--}}
@php($size = $size ?? 'base')
@switch($style)
    @case('gif')
        <span class="hydra-loader hydra-loader--gif hydra-loader--{{ $size }}">@if(!empty($gif))<img src="{{ $gif }}" alt="">@endif</span>
        @break
    @case('hourglass')
        <span class="hydra-loader hydra-loader--hourglass hydra-loader--{{ $size }}"><x-hydra.icon name="hourglass" class="" /></span>
        @break
    @case('dots')
        <span class="hydra-loader hydra-loader--dots hydra-loader--{{ $size }}"><i></i><i></i><i></i></span>
        @break
    @case('shockwave')
        <span class="hydra-loader hydra-loader--shockwave hydra-loader--{{ $size }}"><i></i><i></i><i></i></span>
        @break
    @case('pulse')
        <span class="hydra-loader hydra-loader--pulse hydra-loader--{{ $size }}"><i></i></span>
        @break
    @case('bars')
        <span class="hydra-loader hydra-loader--bars hydra-loader--{{ $size }}"><i></i><i></i><i></i><i></i></span>
        @break
    @default
        <span class="hydra-loader hydra-loader--ring hydra-loader--{{ $size }}"></span>
@endswitch
