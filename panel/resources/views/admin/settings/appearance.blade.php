@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'appearance'])

@section('title')
    {{ __('admin/settings.appearance.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/settings.appearance.title') }}<small>{{ __('admin/settings.appearance.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li class="active">{{ __('admin/settings.breadcrumb') }}</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <form action="{{ route('admin.settings.appearance') }}" method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/settings.appearance.accent') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">{{ __('admin/settings.appearance.color') }}</label>
                                <div class="hydra-color-field">
                                    <input type="color" id="hydraAccentPicker" value="{{ old('accent', $branding->accent()) }}" />
                                    <input type="text" class="form-control" id="hydraAccentInput" name="accent" value="{{ old('accent', $branding->accent()) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" />
                                </div>
                                <p class="text-muted"><small>{!! __('admin/settings.appearance.accent_help', ['default' => e($defaultAccent)]) !!}</small></p>
                            </div>
                            <div class="form-group col-md-8">
                                <label class="control-label">{{ __('admin/settings.appearance.presets') }}</label>
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
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/settings.appearance.logo') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-branding-preview">
                            <img src="{{ $branding->logoUrl() }}" alt="Logo" style="max-height: 64px;" />
                        </div>
                        <div class="form-group">
                            <label class="control-label">{{ __('admin/settings.appearance.logo_upload') }}</label>
                            <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp,.svg" />
                            <p class="text-muted"><small>{{ __('admin/settings.appearance.logo_help') }}</small></p>
                        </div>
                        @if($hasCustomLogo)
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="resetLogo" name="reset_logo" type="checkbox" value="1" />
                                <label for="resetLogo">{{ __('admin/settings.appearance.logo_reset') }}</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/settings.appearance.favicon') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-branding-preview">
                            <img src="{{ $branding->faviconUrl() }}" alt="Favicon" style="width: 48px; height: 48px;" />
                        </div>
                        <div class="form-group">
                            <label class="control-label">{{ __('admin/settings.appearance.favicon_upload') }}</label>
                            <input type="file" name="favicon" class="form-control" accept=".png,.ico,.svg" />
                            <p class="text-muted"><small>{{ __('admin/settings.appearance.favicon_help') }}</small></p>
                        </div>
                        @if($hasCustomFavicon)
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="resetFavicon" name="reset_favicon" type="checkbox" value="1" />
                                <label for="resetFavicon">{{ __('admin/settings.appearance.favicon_reset') }}</label>
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
    </script>
@endsection
