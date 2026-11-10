@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'general'])

@section('title')
    {{ __('admin/design.general.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.general.title') }}<small>{{ __('admin/design.general.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.general') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design') }}" method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.general.accent') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/design.general.color') }}</label>
                                <div class="hydra-color-field">
                                    <input type="color" id="hydraAccentPicker" value="{{ old('accent', $branding->accent()) }}" />
                                    <input type="text" class="form-control" id="hydraAccentInput" name="accent" value="{{ old('accent', $branding->accent()) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" />
                                </div>
                                <p class="text-muted"><small>{!! __('admin/design.general.accent_help', ['default' => e($defaultAccent)]) !!}</small></p>
                            </div>
                            <div class="form-group col-md-8">
                                <label class="control-label">{{ __('admin/design.general.presets') }}</label>
                                <div class="hydra-swatches">
                                    @foreach(['#74c236', '#2fb67c', '#3b9eea', '#6c63ff', '#b16cf0', '#e5534b', '#f08a24', '#e3b341', '#1fb5c4', '#e0569a'] as $preset)
                                        <button type="button" class="hydra-swatch" data-color="{{ $preset }}" style="background: {{ $preset }}" title="{{ $preset }}"></button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <div class="box" id="hydraSurfaces">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.surfaces.title') }}</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">{{ __('admin/design.surfaces.help') }}</p>
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label class="control-label">{{ __('admin/design.general.presets') }}</label>
                                    <div class="hydra-surface-presets">
                                        @foreach($surfacePresets as $key => $preset)
                                            <button type="button" class="hydra-surface-preset" data-surface-preset='@json($preset)'>
                                                <span class="hydra-surface-preset-swatch" style="background: {{ $preset['box'] }}; border-color: {{ $preset['border'] }};">
                                                    <span style="background: {{ $preset['inner'] }};"></span>
                                                </span>
                                                {{ __('admin/design.surfaces.presets.' . $key) }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="row">
                                    @foreach(['box', 'inner', 'border'] as $key)
                                        <div class="form-group col-sm-4 col-md-12 col-lg-4">
                                            <label class="control-label">{{ __('admin/design.surfaces.' . $key) }}</label>
                                            <div class="hydra-color-field">
                                                <input type="color" value="{{ old('surface.' . $key, $surfaces[$key]) }}" data-surface-picker="{{ $key }}" />
                                                <input type="text" class="form-control" name="surface[{{ $key }}]" value="{{ old('surface.' . $key, $surfaces[$key]) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" data-surface="{{ $key }}" />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <p class="text-muted"><small>{{ __('admin/design.surfaces.colors_help') }}</small></p>
                                @foreach(['opacity' => '%', 'border_opacity' => '%', 'blur' => 'px'] as $key => $unit)
                                    <div class="form-group">
                                        <label class="control-label">
                                            {{ __('admin/design.surfaces.' . $key) }}
                                            <span class="text-muted"><span data-surface-value="{{ $key }}">{{ old('surface.' . $key, $surfaces[$key]) }}</span> {{ $unit }}</span>
                                        </label>
                                        <input type="range" name="surface[{{ $key }}]" min="{{ $surfaceLimits[$key][0] }}" max="{{ $surfaceLimits[$key][1] }}" value="{{ old('surface.' . $key, $surfaces[$key]) }}" data-surface="{{ $key }}" />
                                        <p class="text-muted no-margin"><small>{{ __('admin/design.surfaces.' . $key . '_help', ['min' => \Pterodactyl\Services\Hydra\SurfaceService::MIN_FLOAT_OPACITY]) }}</small></p>
                                    </div>
                                @endforeach
                                <button type="button" class="btn btn-sm btn-default" data-surface-preset='@json($surfaceDefaults)'>
                                    <i class="fa fa-undo"></i> {{ __('admin/design.surfaces.reset') }}
                                </button>
                            </div>
                            <div class="col-md-7">
                                <label class="control-label">{{ __('admin/design.surfaces.preview') }}</label>
                                <div class="hydra-surface-stage" data-surface-stage>
                                    <div class="hydra-surface-stage-bg" @if($previewBackground['image']) style="background: url('{{ $previewBackground['image'] }}') center / cover; filter: blur({{ $previewBackground['blur'] }}px);" @endif></div>
                                    <div class="hydra-surface-stage-dim" style="opacity: {{ $previewBackground['dim'] / 100 }};"></div>
                                    <div class="hydra-surface-stage-content">
                                        <div class="pv-sidebar">
                                            <img src="{{ $branding->logoUrl() }}" alt="" />
                                            <span class="pv-nav is-active"></span>
                                            <span class="pv-nav"></span>
                                            <span class="pv-nav"></span>
                                            <span class="pv-nav"></span>
                                        </div>
                                        <div class="pv-main">
                                            <div class="pv-box">
                                                <div class="pv-box-header">{{ __('admin/design.surfaces.preview_box') }}</div>
                                                <div class="pv-box-body">
                                                    <div class="pv-row"><span class="pv-dot"></span>{{ __('admin/design.surfaces.preview_row') }}<small>2 / 4 GB</small></div>
                                                    <div class="pv-row"><span class="pv-dot is-off"></span>{{ __('admin/design.surfaces.preview_row') }} 2<small>0 / 2 GB</small></div>
                                                    <div class="pv-input">{{ __('admin/design.surfaces.preview_input') }}</div>
                                                    <span class="pv-button">{{ __('admin/common.actions.save') }}</span>
                                                </div>
                                            </div>
                                            <div class="pv-box pv-box--small">
                                                <div class="pv-box-header">{{ __('admin/design.surfaces.preview_small') }}</div>
                                                <div class="pv-box-body"><div class="pv-bar"><span></span></div></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-muted"><small>{{ __('admin/design.surfaces.preview_help') }}</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.general.logo') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-branding-preview">
                            <img src="{{ $branding->logoUrl() }}" alt="Logo" style="max-height: 64px;" />
                        </div>
                        <div class="form-group">
                            <label class="control-label">{{ __('admin/design.general.logo_upload') }}</label>
                            <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp,.svg" />
                            <p class="text-muted"><small>{{ __('admin/design.general.logo_help') }}</small></p>
                        </div>
                        @if($hasCustomLogo)
                            <div class="checkbox checkbox-primary">
                                <input id="resetLogo" name="reset_logo" type="checkbox" value="1" />
                                <label for="resetLogo">{{ __('admin/design.general.logo_reset') }}</label>
                            </div>
                        @endif
                        <input type="hidden" name="protect_images" value="0" />
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input id="protectImages" name="protect_images" type="checkbox" value="1" @if(old('protect_images', $branding->protectsImages())) checked @endif />
                            <label for="protectImages">{{ __('admin/design.general.protect_images') }}</label>
                        </div>
                        <p class="text-muted no-margin"><small>{{ __('admin/design.general.protect_images_help') }}</small></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.general.favicon') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-branding-preview">
                            <img src="{{ $branding->faviconUrl() }}" alt="Favicon" style="width: 48px; height: 48px;" />
                        </div>
                        <div class="form-group">
                            <label class="control-label">{{ __('admin/design.general.favicon_upload') }}</label>
                            <input type="file" name="favicon" class="form-control" accept=".png,.ico,.svg" />
                            <p class="text-muted"><small>{{ __('admin/design.general.favicon_help') }}</small></p>
                        </div>
                        @if($hasCustomFavicon)
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="resetFavicon" name="reset_favicon" type="checkbox" value="1" />
                                <label for="resetFavicon">{{ __('admin/design.general.favicon_reset') }}</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-footer">
                        {!! method_field('PATCH') !!}
                        {!! csrf_field() !!}
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var picker = document.getElementById('hydraAccentPicker');
            var input = document.getElementById('hydraAccentInput');
            var valid = /^#[0-9a-fA-F]{6}$/;

            function apply(color) {
                if (!valid.test(color)) return;
                picker.value = color;
                input.value = color;
            }

            picker.addEventListener('input', function () { apply(picker.value); });
            input.addEventListener('input', function () { apply(input.value); });
            document.querySelectorAll('.hydra-swatch').forEach(function (swatch) {
                swatch.addEventListener('click', function () { apply(swatch.dataset.color); });
            });
        })();

        // Boxes & surfaces: keeps pickers, text fields and sliders in sync and
        // shows the result in the preview (same math as SurfaceService).
        (function () {
            var root = document.getElementById('hydraSurfaces');
            if (!root) return;
            var stage = root.querySelector('[data-surface-stage]');
            var valid = /^#[0-9a-fA-F]{6}$/;
            var minFloat = {{ \Pterodactyl\Services\Hydra\SurfaceService::MIN_FLOAT_OPACITY }};

            function field(key) { return root.querySelector('[data-surface="' + key + '"]'); }

            function rgb(hex, ratio) {
                var target = ratio >= 0 ? 255 : 0, amount = Math.abs(ratio || 0);
                return [1, 3, 5].map(function (i) {
                    var channel = parseInt(hex.substr(i, 2), 16);
                    return Math.round(channel + (target - channel) * amount);
                }).join(' ');
            }

            function update() {
                var colors = {};
                ['box', 'inner', 'border'].forEach(function (key) {
                    var value = field(key).value.trim();
                    if (valid.test(value)) {
                        root.querySelector('[data-surface-picker="' + key + '"]').value = value.toLowerCase();
                    }
                    colors[key] = valid.test(value) ? value : root.querySelector('[data-surface-picker="' + key + '"]').value;
                });
                ['opacity', 'border_opacity', 'blur'].forEach(function (key) {
                    root.querySelector('[data-surface-value="' + key + '"]').textContent = field(key).value;
                });

                var alpha = field('opacity').value / 100;
                var blur = parseInt(field('blur').value, 10);
                var vars = {
                    '--pv-box': rgb(colors.box, 0),
                    '--pv-inner': rgb(colors.inner, 0),
                    '--pv-inner-2': rgb(colors.inner, 0.093),
                    '--pv-deep': rgb(colors.box, -0.355),
                    '--pv-line': rgb(colors.border, 0),
                    '--pv-alpha': alpha,
                    '--pv-float-alpha': Math.max(alpha, minFloat / 100),
                    '--pv-line-alpha': field('border_opacity').value / 100,
                    '--pv-backdrop': blur > 0 ? 'blur(' + blur + 'px) saturate(140%)' : 'none'
                };
                Object.keys(vars).forEach(function (name) { stage.style.setProperty(name, vars[name]); });
            }

            root.querySelectorAll('[data-surface-picker]').forEach(function (picker) {
                picker.addEventListener('input', function () {
                    field(picker.dataset.surfacePicker).value = picker.value;
                    update();
                });
            });
            root.querySelectorAll('[data-surface]').forEach(function (input) {
                input.addEventListener('input', update);
            });
            root.querySelectorAll('[data-surface-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var preset = JSON.parse(button.dataset.surfacePreset);
                    Object.keys(preset).forEach(function (key) {
                        if (field(key)) field(key).value = preset[key];
                    });
                    update();
                });
            });

            update();
        })();
    </script>
@endsection
