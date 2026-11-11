@extends('layouts.admin')

@php($canEdit = Auth::user()->hasAdminPermission('knowledgebase.update'))
@php($canDelete = Auth::user()->hasAdminPermission('knowledgebase.delete'))
@php($editing = $post->exists)
@php($category = $post->category_id ? \Pterodactyl\Models\HydraKbCategory::query()->find($post->category_id) : null)
{{-- The required default language comes first. --}}
@php($languages = array_key_exists($defaultLanguage, $languages) ? [$defaultLanguage => $languages[$defaultLanguage]] + $languages : $languages)

@section('hydra-sidebar')
    @include('admin.knowledgebase.partials.sidebar', ['post' => $editing ? $post : null, 'category' => $editing ? null : $category])
@endsection

@section('title')
    {{ $editing ? $post->title() : __('admin/knowledgebase.new_post') }} – {{ __('admin/knowledgebase.title') }}
@endsection

@section('content-header')
    <h1>{{ $editing ? $post->title() : __('admin/knowledgebase.new_post') }}<small>{{ __('admin/knowledgebase.post_subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.knowledgebase') }}">{{ __('admin/knowledgebase.title') }}</a></li>
        <li class="active">{{ $editing ? $post->title() : __('admin/knowledgebase.new_post') }}</li>
    </ol>
@endsection

@section('content')
    @if($tree->isEmpty())
        <div class="alert alert-warning">{!! __('admin/knowledgebase.need_category', ['url' => route('admin.knowledgebase') . '#new-category']) !!}</div>
    @endif
    <form action="{{ $editing ? route('admin.knowledgebase.post', $post->id) : route('admin.knowledgebase.posts') }}" method="POST" id="kbPostForm">
        {!! csrf_field() !!}
        @if($editing) {!! method_field('PATCH') !!} @endif
        <fieldset @disabled(!$canEdit)>
            <div class="row">
                <div class="col-lg-8">
                    <div class="box">
                        <div class="box-header with-border">
                            <ul class="hydra-kb-languages" role="tablist">
                                @foreach($languages as $code => $language)
                                    <li>
                                        <button type="button" class="@if($loop->first) active @endif" data-kb-language="{{ $code }}">
                                            {{ $language }}
                                            @if($code === $defaultLanguage)<span class="hydra-kb-required">{{ __('admin/knowledgebase.required') }}</span>@endif
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="box-body">
                            @foreach($languages as $code => $language)
                                <div data-kb-pane="{{ $code }}" @unless($loop->first) hidden @endunless>
                                    <div class="form-group">
                                        <label class="control-label" for="kbTitle_{{ $code }}">{{ __('admin/knowledgebase.title_field') }} ({{ $language }})</label>
                                        <input type="text" id="kbTitle_{{ $code }}" name="titles[{{ $code }}]" class="form-control input-lg" maxlength="{{ \Pterodactyl\Models\HydraKbPost::MAX_TITLE }}"
                                               value="{{ old("titles.$code", $post->titles[$code] ?? '') }}" @if($code === $defaultLanguage) required @endif
                                               @if($code !== $defaultLanguage) placeholder="{{ __('admin/knowledgebase.fallback_hint', ['language' => $languages[$defaultLanguage] ?? $defaultLanguage]) }}" @endif>
                                    </div>
                                    @include('admin.knowledgebase.partials.editor', [
                                        'name' => "contents[$code]",
                                        'value' => old("contents.$code", $post->contents[$code] ?? ''),
                                        'required' => $code === $defaultLanguage,
                                    ])
                                </div>
                            @endforeach
                            <p class="text-muted small no-margin">{{ __('admin/knowledgebase.languages_help', ['language' => $languages[$defaultLanguage] ?? $defaultLanguage]) }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/knowledgebase.settings') }}</h3>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <label class="control-label">{{ __('admin/knowledgebase.category') }} <span class="field-required"></span></label>
                                <select name="category_id" class="form-control" required>
                                    @foreach($tree as $main)
                                        <option value="{{ $main->id }}" @selected((int) old('category_id', $post->category_id) === $main->id)>{{ $main->name() }}</option>
                                        @foreach($main->children as $child)
                                            <option value="{{ $child->id }}" @selected((int) old('category_id', $post->category_id) === $child->id)>&nbsp;&nbsp;&nbsp;↳ {{ $child->name() }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" id="kbPublished" name="published" value="1" @checked(old('published', $post->published))>
                                <label for="kbPublished">{{ __('admin/knowledgebase.published') }}</label>
                            </div>
                            <p class="text-muted small">{{ __('admin/knowledgebase.published_help') }}</p>
                            @if($editing)
                                <div class="form-group">
                                    <label class="control-label">{{ __('admin/knowledgebase.slug') }}</label>
                                    <input type="text" name="slug" class="form-control" maxlength="120" value="{{ old('slug', $post->slug) }}">
                                    <p class="text-muted small no-margin">{{ $addon->url() }}/{{ $post->slug }}</p>
                                </div>
                                <div class="form-group">
                                    <label class="control-label">{{ __('admin/knowledgebase.sort') }}</label>
                                    <input type="number" name="sort" min="0" max="100000" class="form-control" value="{{ old('sort', $post->sort) }}">
                                    <p class="text-muted small no-margin">{{ __('admin/knowledgebase.sort_help') }}</p>
                                </div>
                                <p class="text-muted small no-margin">
                                    {{ __('admin/knowledgebase.meta', ['author' => $post->author?->username ?? '—', 'created' => $post->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i'), 'updated' => $post->updated_at->timezone(config('app.timezone'))->format('d.m.Y H:i')]) }}
                                </p>
                            @endif
                        </div>
                        <div class="box-footer">
                            @if($canEdit)
                                <button type="submit" class="btn btn-sm btn-primary">{{ $editing ? __('admin/common.actions.save') : __('admin/knowledgebase.create') }}</button>
                            @endif
                            @if($editing && $post->published)
                                <a href="{{ $addon->url() }}/{{ $post->slug }}" target="_blank" rel="noopener" class="btn btn-sm btn-default pull-right"><x-hydra.icon name="external-link" /> {{ __('admin/knowledgebase.view') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
    @if($editing && $canDelete)
        <div class="row">
            <div class="col-lg-4 col-lg-offset-8">
                <form action="{{ route('admin.knowledgebase.post', $post->id) }}" method="POST" data-kb-confirm="{{ __('admin/knowledgebase.delete_post_confirm') }}" class="text-right">
                    {!! csrf_field() !!}
                    {!! method_field('DELETE') !!}
                    <button type="submit" class="btn btn-sm btn-danger"><x-hydra.icon name="trash" /> {{ __('admin/knowledgebase.delete_post') }}</button>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('footer-scripts')
    @parent
    <script src="/hydra/ticket-editor.js?v={{ substr(md5_file(public_path('hydra/ticket-editor.js')), 0, 8) }}"></script>
    @include('admin.knowledgebase.partials.confirm')
    <script>
        // Language tabs. A required field in a hidden tab shows its tab first.
        (function () {
            var form = document.getElementById('kbPostForm');
            function show(code) {
                form.querySelectorAll('[data-kb-language]').forEach(function (b) { b.classList.toggle('active', b.dataset.kbLanguage === code); });
                form.querySelectorAll('[data-kb-pane]').forEach(function (p) { p.hidden = p.dataset.kbPane !== code; });
            }
            form.querySelectorAll('[data-kb-language]').forEach(function (button) {
                button.addEventListener('click', function () { show(button.dataset.kbLanguage); });
            });
            form.addEventListener('invalid', function (event) {
                var pane = event.target.closest('[data-kb-pane]');
                if (pane && pane.hidden) show(pane.dataset.kbPane);
            }, true);
        })();
    </script>
@endsection
