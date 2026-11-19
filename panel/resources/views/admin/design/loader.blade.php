@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'loader'])

@php($canSave = Auth::user()->hasAdminPermission('design.update'))
@php($current = old('style', $style))

@section('title')
    {{ __('admin/design.tabs.loader') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.tabs.loader') }}<small>{{ __('admin/design.loader.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.loader') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.loader') }}" method="POST" enctype="multipart/form-data" id="hydraLoaderForm">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/design.loader.style') }}</h3>
            </div>
            <div class="box-body">
                <div class="hydra-loader-picker" id="hydraLoaderPicker" style="--hydra-loader-speed: {{ old('speed', $speed) / 100 }};">
                    @foreach(\Pterodactyl\Services\Hydra\LoaderDesignService::STYLES as $option)
                        <label class="hydra-loader-option">
                            <input type="radio" name="style" value="{{ $option }}" @checked($current === $option) @disabled(!$canSave)>
                            <span class="hydra-loader-option-card">
                                <span class="hydra-loader-option-stage">
                                    @if($option === 'gif' && !$gifUrl)
                                        <x-hydra.icon name="image-plus" class="hydra-loader-option-empty" />
                                    @else
                                        @include('partials.hydra.loader', ['style' => $option, 'gif' => $gifUrl, 'size' => 'large'])
                                    @endif
                                </span>
                                <span class="hydra-loader-option-name">{{ __("admin/design.loader.styles.$option") }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.loader.speed') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-range-row">
                            <input type="range" name="speed" id="hydraLoaderSpeed" min="{{ \Pterodactyl\Services\Hydra\LoaderDesignService::SPEED[0] }}" max="{{ \Pterodactyl\Services\Hydra\LoaderDesignService::SPEED[1] }}" step="5" value="{{ old('speed', $speed) }}" @disabled(!$canSave)>
                            <output for="hydraLoaderSpeed" id="hydraLoaderSpeedValue">{{ old('speed', $speed) }} %</output>
                        </div>
                        <p class="text-muted no-margin"><small>{{ __('admin/design.loader.speed_help') }}</small></p>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.loader.sizes') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-loader-sizes" id="hydraLoaderSizes">
                            @foreach(['small', 'base', 'large'] as $size)
                                <span data-loader-size="{{ $size }}">@include('partials.hydra.loader', ['style' => $current === 'gif' && !$gifUrl ? 'ring' : $current, 'gif' => $gifUrl, 'size' => $size])</span>
                            @endforeach
                            <span class="hydra-loader-sizes-button"><span class="btn btn-primary btn-sm" tabindex="-1">@include('partials.hydra.loader', ['style' => $current === 'gif' && !$gifUrl ? 'ring' : $current, 'gif' => $gifUrl, 'size' => 'small']) {{ __('admin/design.loader.button_sample') }}</span></span>
                        </div>
                        <p class="text-muted no-margin"><small>{{ __('admin/design.loader.sizes_help') }}</small></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.loader.gif') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group @error('gif') has-error @enderror">
                            <input type="file" name="gif" class="form-control" accept=".gif,.webp,.png,.svg" @disabled(!$canSave)>
                            @error('gif')<p class="text-danger small">{{ $message }}</p>@enderror
                            <p class="text-muted"><small>{{ __('admin/design.loader.gif_help') }}</small></p>
                        </div>
                        @if($gifUrl)
                            <div class="checkbox checkbox-primary no-margin">
                                <input id="removeGif" name="remove_gif" type="checkbox" value="1" @disabled(!$canSave)>
                                <label for="removeGif">{{ __('admin/design.loader.gif_remove') }}</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if($canSave)
            <div class="box">
                <div class="box-footer">
                    <button type="submit" class="btn btn-success pull-right">{{ __('admin/common.actions.save') }}</button>
                </div>
            </div>
        @endif
    </form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var picker = document.getElementById('hydraLoaderPicker');
            var speed = document.getElementById('hydraLoaderSpeed');
            var output = document.getElementById('hydraLoaderSpeedValue');
            var sizes = document.getElementById('hydraLoaderSizes');

            // Speed: every preview on the page follows the slider.
            speed.addEventListener('input', function () {
                output.textContent = speed.value + ' %';
                document.getElementById('hydraLoaderForm').style.setProperty('--hydra-loader-speed', speed.value / 100);
            });
            document.getElementById('hydraLoaderForm').style.setProperty('--hydra-loader-speed', speed.value / 100);
            picker.style.removeProperty('--hydra-loader-speed');

            // Sizes: copy the picked template into the size examples.
            picker.addEventListener('change', function (event) {
                var card = event.target.closest('.hydra-loader-option');
                var loader = card && card.querySelector('.hydra-loader');
                if (!loader) return;
                sizes.querySelectorAll('.hydra-loader').forEach(function (old) {
                    var size = (old.className.match(/hydra-loader--(small|base|large)/) || [])[1] || 'base';
                    var copy = loader.cloneNode(true);
                    copy.className = copy.className.replace(/hydra-loader--(small|base|large)/, 'hydra-loader--' + size);
                    old.replaceWith(copy);
                });
            });
        })();
    </script>
@endsection
