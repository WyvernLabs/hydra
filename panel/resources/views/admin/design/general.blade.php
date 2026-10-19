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
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="resetLogo" name="reset_logo" type="checkbox" value="1" />
                                <label for="resetLogo">{{ __('admin/design.general.logo_reset') }}</label>
                            </div>
                        @endif
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
    </script>
@endsection
