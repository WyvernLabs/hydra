@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'login'])

@php($canSave = Auth::user()->hasAdminPermission('design.update'))

@section('designer-preview', json_encode([
    ['key' => 'login', 'label' => __('admin/design.tabs.login'), 'url' => '/auth/login?hydra_designer=1'],
]))

@section('title')
    {{ __('admin/design.login.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.login.title') }}<small>{{ __('admin/design.login.subtitle_page') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.designer.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.login') }}</li>
    </ol>
@endsection

@section('content')
    <form action="{{ route('admin.design.login') }}" method="POST" enctype="multipart/form-data">
        {!! csrf_field() !!}
        {!! method_field('PATCH') !!}
        <fieldset @disabled(!$canSave)>
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/design.login.style') }}</h3>
                </div>
                <div class="box-body">
                    <div class="hydra-login-styles">
                        @foreach(\Pterodactyl\Services\Hydra\LoginDesignService::STYLES as $style)
                            <label class="hydra-login-style" data-style="{{ $style }}">
                                <input type="radio" name="login_style" value="{{ $style }}" @checked(old('login_style', $values['style']) === $style)>
                                <span class="hydra-login-style-art" aria-hidden="true">
                                    @if($style === 'classic')
                                        <span class="art-bg"><span class="art-card"><i></i><i></i><i class="art-btn"></i></span></span>
                                    @elseif($style === 'split')
                                        <span class="art-bg"><span class="art-split"><span class="art-form"><i></i><i></i><i class="art-btn"></i></span><span class="art-image"></span></span></span>
                                    @else
                                        <span class="art-side"><span class="art-form"><i></i><i></i><i class="art-btn"></i></span><span class="art-image"></span></span>
                                    @endif
                                </span>
                                <strong>{{ __("admin/design.login.styles.$style") }}</strong>
                                <small>{{ __("admin/design.login.styles.{$style}_help") }}</small>
                            </label>
                        @endforeach
                    </div>
                    <div class="checkbox checkbox-primary no-margin-bottom" data-login-mirror>
                        <input id="loginMirror" name="login_mirror" type="checkbox" value="1" @checked(old('login_mirror', $values['mirror']))>
                        <label for="loginMirror">{{ __('admin/design.login.mirror') }}</label>
                    </div>
                    <p class="text-muted small no-margin">{{ __('admin/design.login.mirror_help') }}</p>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/design.login.image') }}</h3>
                </div>
                <div class="box-body">
                    @if($values['image'])
                        <div class="hydra-login-image-current" style="background-image: url('{{ $values['image'] }}')"></div>
                    @endif
                    <div class="form-group">
                        <input type="file" name="login_image" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp" data-current="{{ $values['image'] }}">
                        <p class="text-muted small">{!! __('admin/design.login.image_help', ['url' => route('admin.design.backgrounds')]) !!}</p>
                    </div>
                    @if($values['image'])
                        <div class="checkbox checkbox-primary no-margin">
                            <input id="loginResetImage" name="reset_login_image" type="checkbox" value="1">
                            <label for="loginResetImage">{{ __('admin/design.login.image_reset') }}</label>
                        </div>
                    @endif
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/design.backgrounds.login') }}</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted small">{{ __('admin/design.backgrounds.login_help') }}</p>
                    @include('partials.admin.hydra.background-editor', [
                        'prefix' => 'bg_login',
                        'background' => $background,
                        'resetName' => 'reset_background',
                        'resetValue' => 'login',
                    ])
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/design.login.texts') }}</h3>
                </div>
                <div class="box-body">
                    @foreach($languages as $code => $language)
                        <div class="hydra-login-texts">
                            <div class="hydra-login-texts-lang">{{ $language }}</div>
                            <div class="form-group">
                                <label class="control-label" for="loginTitle_{{ $code }}">{{ __('admin/design.login.heading') }}</label>
                                <input type="text" id="loginTitle_{{ $code }}" name="texts[title][{{ $code }}]" class="form-control"
                                       maxlength="{{ \Pterodactyl\Services\Hydra\LoginDesignService::TEXTS['title'] }}"
                                       value="{{ old("texts.title.$code", $values['texts']['title'][$code] ?? '') }}" placeholder="{{ $defaults[$code]['title'] }}">
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="loginSubtitle_{{ $code }}">{{ __('admin/design.login.subtitle') }}</label>
                                <input type="text" id="loginSubtitle_{{ $code }}" name="texts[subtitle][{{ $code }}]" class="form-control"
                                       maxlength="{{ \Pterodactyl\Services\Hydra\LoginDesignService::TEXTS['subtitle'] }}"
                                       value="{{ old("texts.subtitle.$code", $values['texts']['subtitle'][$code] ?? '') }}" placeholder="{{ $defaults[$code]['subtitle'] }}">
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="loginInfo_{{ $code }}">{{ __('admin/design.login.info') }}</label>
                                <textarea id="loginInfo_{{ $code }}" name="texts[info][{{ $code }}]" class="form-control" rows="2"
                                          maxlength="{{ \Pterodactyl\Services\Hydra\LoginDesignService::TEXTS['info'] }}"
                                          placeholder="{{ __('admin/design.login.info_placeholder') }}">{{ old("texts.info.$code", $values['texts']['info'][$code] ?? '') }}</textarea>
                            </div>
                        </div>
                    @endforeach
                    <p class="text-muted small no-margin">{{ __('admin/design.login.texts_help') }}</p>
                </div>
                @if($canSave)
                    <div class="box-footer">
                        <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    </div>
                @endif
            </div>
        </fieldset>
    </form>
@endsection

@section('footer-scripts')
    @parent
    @include('partials.admin.hydra.background-editor-script')
    <script>
        // Mirroring only exists for the split and side styles.
        (function () {
            var mirror = document.querySelector('[data-login-mirror] input');
            function update() {
                var style = document.querySelector('[name="login_style"]:checked');
                mirror.disabled = !style || style.value === 'classic';
            }
            document.querySelectorAll('[name="login_style"]').forEach(function (input) { input.addEventListener('change', update); });
            update();
        })();
    </script>
@endsection
