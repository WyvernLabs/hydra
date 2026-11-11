@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'backgrounds'])

@section('designer-preview', json_encode([
    ['key' => 'dashboard', 'label' => __('admin/design.designer.page_dashboard'), 'url' => '/?hydra_designer=1'],
    ['key' => 'login', 'label' => __('admin/design.designer.page_login'), 'url' => '/auth/login?hydra_designer=1'],
]))

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('title')
    {{ __('admin/design.tabs.backgrounds') }}
@endsection
@php($branding = app(\Pterodactyl\Services\Hydra\BrandingService::class))

@section('content-header')
    <h1>{{ __('admin/design.tabs.backgrounds') }}<small>{{ __('admin/design.backgrounds.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.backgrounds') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.backgrounds') }}" method="POST" enctype="multipart/form-data">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
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
            @foreach($backgrounds as $slot => $background)
                <div class="col-md-6">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __("admin/design.backgrounds.$slot") }}</h3>
                        </div>
                        <div class="box-body">
                            <p class="text-muted small">{{ __("admin/design.backgrounds.{$slot}_help") }}</p>
                            @include('partials.admin.hydra.background-editor', [
                                'prefix' => "bg_$slot",
                                'background' => $background,
                                'resetName' => 'reset_background',
                                'resetValue' => $slot,
                            ])
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if($canSave)
            <div class="row">
                <div class="col-xs-12">
                    <div class="box">
                        <div class="box-footer">
                            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </form>
    <div class="row">
        <div class="col-xs-12">
            <p class="text-muted small">{!! __('admin/design.backgrounds.eggs_hint', ['url' => route('admin.design.eggs')]) !!}</p>
            <p class="text-muted small">{!! __('admin/design.backgrounds.login_hint', ['url' => route('admin.design.login')]) !!}</p>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    @include('partials.admin.hydra.background-editor-script')
@endsection
