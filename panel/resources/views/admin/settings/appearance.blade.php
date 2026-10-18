@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'appearance'])

@section('title')
    Appearance
@endsection

@section('content-header')
    <h1>Appearance<small>Accent color, logo and favicon of the panel.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <form action="{{ route('admin.settings.appearance') }}" method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Accent Color</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Color</label>
                                <div class="hydra-color-field">
                                    <input type="color" id="hydraAccentPicker" value="{{ old('accent', $branding->accent()) }}" />
                                    <input type="text" class="form-control" id="hydraAccentInput" name="accent" value="{{ old('accent', $branding->accent()) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" />
                                </div>
                                <p class="text-muted"><small>Used for buttons, links, the active navigation entry and highlights in the client and admin area. Default: <code>{{ $defaultAccent }}</code>.</small></p>
                            </div>
                            <div class="form-group col-md-8">
                                <label class="control-label">Presets</label>
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
                        <h3 class="box-title">Logo</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-branding-preview">
                            <img src="{{ $branding->logoUrl() }}" alt="Logo" style="max-height: 64px;" />
                        </div>
                        <div class="form-group">
                            <label class="control-label">Upload new logo</label>
                            <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp,.svg" />
                            <p class="text-muted"><small>Shown at the top of the sidebar and on the login page. PNG, JPG, GIF, WEBP or SVG, max. 2 MB. A transparent image of about 400×120 px works best.</small></p>
                        </div>
                        @if($hasCustomLogo)
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="resetLogo" name="reset_logo" type="checkbox" value="1" />
                                <label for="resetLogo">Restore the default logo</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Favicon</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-branding-preview">
                            <img src="{{ $branding->faviconUrl() }}" alt="Favicon" style="width: 48px; height: 48px;" />
                        </div>
                        <div class="form-group">
                            <label class="control-label">Upload new favicon</label>
                            <input type="file" name="favicon" class="form-control" accept=".png,.ico,.svg" />
                            <p class="text-muted"><small>Icon shown in the browser tab. PNG, ICO or SVG, max. 1 MB, ideally square (e.g. 256×256 px).</small></p>
                        </div>
                        @if($hasCustomFavicon)
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="resetFavicon" name="reset_favicon" type="checkbox" value="1" />
                                <label for="resetFavicon">Restore the default favicon</label>
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
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save</button>
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
