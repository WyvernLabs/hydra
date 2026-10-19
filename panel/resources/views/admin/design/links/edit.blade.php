@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'links'])

@php
    $canSave = Auth::user()->hasAdminPermission('design.update');
    $type = old('type', $link->type ?? 'page');
    $titles = old('title', $link->title ?? []);
    $contents = old('content', $link->content ?? []);
@endphp

@section('title')
    {{ $link ? $link->localizedTitle() : __('admin/design.links.create_title') }}
@endsection

@section('content-header')
    <h1>{{ $link ? $link->localizedTitle() : __('admin/design.links.create_title') }}<small>{{ __('admin/design.links.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design.links') }}">{{ __('admin/design.links.title') }}</a></li>
        <li class="active">{{ $link ? $link->localizedTitle() : __('admin/common.actions.create') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ $link ? route('admin.design.links.view', $link->id) : route('admin.design.links.new') }}" method="POST" id="linkForm">
        {!! csrf_field() !!}
        @if($link) {!! method_field('PATCH') !!} @endif
        <div class="row">
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.links.details') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">{{ __('admin/design.links.type') }}</label>
                            <div>
                                <label class="radio-inline"><input type="radio" name="type" value="page" @checked($type === 'page')> {{ __('admin/design.links.types.page') }}</label>
                                <label class="radio-inline"><input type="radio" name="type" value="url" @checked($type === 'url')> {{ __('admin/design.links.types.url') }}</label>
                            </div>
                        </div>
                        @foreach($languages as $code => $language)
                            <div class="form-group">
                                <label class="control-label" for="title_{{ $code }}">{{ __('admin/design.links.link_title') }} ({{ $language }})</label>
                                <input type="text" id="title_{{ $code }}" name="title[{{ $code }}]" class="form-control" maxlength="64" value="{{ $titles[$code] ?? '' }}">
                            </div>
                        @endforeach
                        <p class="text-muted small">{{ __('admin/design.links.title_help') }}</p>
                        <div class="form-group">
                            <label class="control-label" for="icon">{{ __('admin/design.links.icon') }}</label>
                            <select id="icon" name="icon" class="form-control">
                                @foreach($icons as $icon)
                                    <option value="{{ $icon }}" @selected(old('icon', $link->icon ?? 'document-text') === $icon)>{{ __('admin/design.links.icons.' . $icon) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" data-for-type="page">
                            <label class="control-label" for="slug">{{ __('admin/design.links.slug') }}</label>
                            <div class="input-group">
                                <span class="input-group-addon">/pages/</span>
                                <input type="text" id="slug" name="slug" class="form-control" maxlength="64" value="{{ old('slug', $link->slug ?? '') }}" placeholder="imprint">
                            </div>
                            <p class="text-muted small">{{ __('admin/design.links.slug_help') }}</p>
                        </div>
                        <div class="form-group" data-for-type="url">
                            <label class="control-label" for="url">{{ __('admin/design.links.url') }}</label>
                            <input type="url" id="url" name="url" class="form-control" maxlength="2048" value="{{ old('url', $link->url ?? '') }}" placeholder="https://discord.gg/…">
                            <p class="text-muted small">{{ __('admin/design.links.url_help') }}</p>
                        </div>
                        <div class="form-group">
                            <label class="control-label" for="sort">{{ __('admin/design.links.sort') }}</label>
                            <input type="number" id="sort" name="sort" class="form-control" min="0" max="9999" value="{{ old('sort', $link->sort ?? 0) }}">
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled', $link->enabled ?? true))>
                            <label for="enabled">{{ __('admin/design.links.enabled') }}</label>
                        </div>
                        <div class="checkbox checkbox-primary" data-for-type="page">
                            <input type="hidden" name="public" value="0">
                            <input type="checkbox" id="public" name="public" value="1" @checked(old('public', $link->public ?? false))>
                            <label for="public">{{ __('admin/design.links.public') }}</label>
                            <p class="text-muted small">{{ __('admin/design.links.public_help') }}</p>
                        </div>
                    </div>
                    @if($canSave)
                        <div class="box-footer">
                            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-md-7" data-for-type="page">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.links.content') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="nav-tabs-custom" style="box-shadow: none; margin-bottom: 0;">
                            <ul class="nav nav-tabs">
                                @foreach($languages as $code => $language)
                                    <li @if($loop->first) class="active" @endif><a href="#content_{{ $code }}" data-toggle="tab">{{ $language }}</a></li>
                                @endforeach
                            </ul>
                            <div class="tab-content" style="padding: 12px 0 0;">
                                @foreach($languages as $code => $language)
                                    <div class="tab-pane @if($loop->first) active @endif" id="content_{{ $code }}">
                                        <textarea name="content[{{ $code }}]" class="form-control hydra-markdown-input" rows="18" data-language="{{ $code }}">{{ $contents[$code] ?? '' }}</textarea>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <p class="text-muted small" style="margin-top: 8px;">{!! __('admin/design.links.markdown_help') !!}</p>
                        <button type="button" class="btn btn-sm btn-default" id="previewButton">{{ __('admin/design.links.preview') }}</button>
                        <div class="hydra-markdown hydra-markdown-preview" id="preview" hidden></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    @if($link && $canSave)
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.links.delete_title') }}</h3>
                    </div>
                    <div class="box-body">
                        <p class="no-margin">{{ __('admin/design.links.delete_notice') }}</p>
                    </div>
                    <div class="box-footer">
                        <form action="{{ route('admin.design.links.delete', $link->id) }}" method="POST">
                            {!! csrf_field() !!}
                            {!! method_field('DELETE') !!}
                            <button type="submit" class="btn btn-sm btn-danger pull-right">{{ __('admin/design.links.delete') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            // Show only the fields of the chosen type.
            function toggle() {
                var type = document.querySelector('input[name=type]:checked').value;
                document.querySelectorAll('[data-for-type]').forEach(function (el) {
                    el.hidden = el.dataset.forType !== type;
                });
            }
            document.querySelectorAll('input[name=type]').forEach(function (el) { el.addEventListener('change', toggle); });
            toggle();

            // Fill the address from the title as long as it was not typed by hand.
            var slug = document.getElementById('slug');
            var title = document.querySelector('input[name^="title["]');
            var touched = slug.value.length > 0;
            slug.addEventListener('input', function () { touched = true; });
            title.addEventListener('input', function () {
                if (touched) return;
                slug.value = title.value.toLowerCase()
                    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').substr(0, 64);
            });

            // Preview of the language tab that is open.
            var preview = document.getElementById('preview');
            document.getElementById('previewButton').addEventListener('click', function () {
                var textarea = document.querySelector('.tab-pane.active .hydra-markdown-input');
                $.ajax({
                    type: 'POST',
                    url: '{{ route('admin.design.links.preview') }}',
                    data: { _token: '{{ csrf_token() }}', content: textarea.value },
                }).done(function (data) {
                    preview.innerHTML = data.html;
                    preview.hidden = false;
                });
            });

            // Spoilers open on click, like on Discord.
            preview.addEventListener('click', function (event) {
                if (event.target.classList.contains('md-spoiler')) event.target.classList.add('is-revealed');
            });
        })();
    </script>
@endsection
