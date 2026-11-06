@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'announcements'])

@php
    $canSave = Auth::user()->hasAdminPermission('announcements.update');
    $heading = $announcement ? $announcement->localized($announcement->title) : __('admin/announcements.new');
    $titles = old('title', $announcement->title ?? []);
    $descriptions = old('description', $announcement->description ?? []);
    $buttons = old('button_text', $announcement->button_text ?? []);
    $selectedType = old('type', $announcement->type ?? 'info');
    $scope = old('scope', $announcement && !$announcement->isGeneral() ? 'nodes' : 'general');
    $selectedNodes = array_map('intval', old('nodes', $announcement->nodes ?? []));
    $sent = !is_null(old('_token'));
    $enabled = $sent ? (bool) old('enabled') : ($announcement->enabled ?? true);
    $dismissible = $sent ? (bool) old('dismissible') : ($announcement->dismissible ?? false);
    $expires = old('expires_at', $announcement?->expires_at?->format('Y-m-d\TH:i'));
@endphp

@section('title')
    {{ $heading }}
@endsection

@section('content-header')
    <h1>{{ $heading }}<small>{{ __('admin/announcements.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design.announcements') }}">{{ __('admin/announcements.title') }}</a></li>
        <li class="active">{{ $announcement ? __('admin/common.actions.edit') : __('admin/common.actions.create') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('admin/announcements.preview') }}</h3>
                </div>
                <div class="box-body">
                    {{-- Same markup and styles as the client area. --}}
                    <div id="announcementPreview" class="hydra-announcement" data-type="{{ $selectedType }}">
                        @foreach(\Pterodactyl\Models\HydraAnnouncement::TYPES as $type => $icon)
                            <span class="hydra-announcement-icon" data-icon-for="{{ $type }}" @if($type !== $selectedType) style="display: none;" @endif><x-hydra.icon :name="$icon" class="" /></span>
                        @endforeach
                        <div class="hydra-announcement-text">
                            <strong id="previewTitle"></strong>
                            <p id="previewDescription"></p>
                        </div>
                        <a id="previewButton" class="hydra-announcement-button" href="#" onclick="return false;" style="display: none;"></a>
                        <span id="previewClose" class="hydra-announcement-close" style="display: none;"><x-hydra.icon name="x" class="" /></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ $announcement ? route('admin.design.announcements.view', $announcement->id) : route('admin.design.announcements.new') }}" method="POST" id="announcementForm">
        {!! csrf_field() !!}
        @if($announcement) {!! method_field('PATCH') !!} @endif
        <fieldset @disabled(!$canSave)>
            <div class="row">
                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/announcements.content') }}</h3>
                        </div>
                        <div class="box-body">
                            @foreach($languages as $code => $language)
                                <div class="form-group">
                                    <label class="control-label" for="title_{{ $code }}">{{ __('admin/announcements.heading') }} ({{ $language }})</label>
                                    <input type="text" id="title_{{ $code }}" name="title[{{ $code }}]" class="form-control" maxlength="191" value="{{ $titles[$code] ?? '' }}" data-preview="title">
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="description_{{ $code }}">{{ __('admin/announcements.description') }} ({{ $language }})</label>
                                    <textarea id="description_{{ $code }}" name="description[{{ $code }}]" class="form-control" rows="3" maxlength="2000" data-preview="description">{{ $descriptions[$code] ?? '' }}</textarea>
                                </div>
                                <div class="form-group">
                                    <label class="control-label" for="button_{{ $code }}">{{ __('admin/announcements.button_text') }} ({{ $language }})</label>
                                    <input type="text" id="button_{{ $code }}" name="button_text[{{ $code }}]" class="form-control" maxlength="64" value="{{ $buttons[$code] ?? '' }}" data-preview="button">
                                </div>
                            @endforeach
                            <p class="text-muted small no-margin">{{ __('admin/announcements.languages_help') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ __('admin/announcements.settings') }}</h3>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <label class="control-label" for="type">{{ __('admin/announcements.type') }}</label>
                                <select id="type" name="type" class="form-control">
                                    @foreach(array_keys(\Pterodactyl\Models\HydraAnnouncement::TYPES) as $type)
                                        <option value="{{ $type }}" @selected($selectedType === $type)>{{ __('admin/announcements.types.' . $type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="scope">{{ __('admin/announcements.target') }}</label>
                                <select id="scope" name="scope" class="form-control">
                                    <option value="general" @selected($scope === 'general')>{{ __('admin/announcements.general') }}</option>
                                    <option value="nodes" @selected($scope === 'nodes')>{{ __('admin/announcements.per_node') }}</option>
                                </select>
                                <p class="text-muted small no-margin" data-scope="general">{{ __('admin/announcements.general_help') }}</p>
                            </div>
                            <div class="form-group" data-scope="nodes">
                                <label class="control-label" for="nodes">{{ __('admin/announcements.nodes') }}</label>
                                <select id="nodes" name="nodes[]" class="form-control" multiple>
                                    @foreach($allNodes as $node)
                                        <option value="{{ $node->id }}" @selected(in_array($node->id, $selectedNodes, true))>{{ $node->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-muted small no-margin">{{ __('admin/announcements.nodes_help') }}</p>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="button_url">{{ __('admin/announcements.button_url') }}</label>
                                <input type="text" id="button_url" name="button_url" class="form-control" maxlength="2048" value="{{ old('button_url', $announcement->button_url ?? '') }}" placeholder="https://">
                                <p class="text-muted small no-margin">{{ __('admin/announcements.button_url_help') }}</p>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="sort">{{ __('admin/announcements.sort') }}</label>
                                <input type="number" min="0" max="9999" id="sort" name="sort" class="form-control" value="{{ old('sort', $announcement->sort ?? 0) }}">
                                <p class="text-muted small no-margin">{{ __('admin/announcements.sort_help') }}</p>
                            </div>
                            <div class="form-group">
                                <label class="control-label" for="expires_at">{{ __('admin/announcements.expires_at') }}</label>
                                <input type="datetime-local" id="expires_at" name="expires_at" class="form-control" value="{{ $expires }}">
                                <p class="text-muted small no-margin">{{ __('admin/announcements.expires_at_help', ['timezone' => config('app.timezone')]) }}</p>
                            </div>
                            <div class="form-group">
                                <div class="checkbox checkbox-primary no-margin-bottom">
                                    <input type="hidden" name="dismissible" value="0">
                                    <input type="checkbox" id="dismissible" name="dismissible" value="1" @checked($dismissible)>
                                    <label for="dismissible" class="strong">{{ __('admin/announcements.dismissible') }}</label>
                                </div>
                                <p class="text-muted small no-margin">{{ __('admin/announcements.dismissible_help', ['hours' => \Pterodactyl\Models\HydraAnnouncement::DISMISS_HOURS]) }}</p>
                            </div>
                            <div class="form-group no-margin-bottom">
                                <div class="checkbox checkbox-primary no-margin-bottom">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" id="enabled" name="enabled" value="1" @checked($enabled)>
                                    <label for="enabled" class="strong">{{ __('admin/announcements.enabled') }}</label>
                                </div>
                            </div>
                        </div>
                        @if($canSave)
                            <div class="box-footer">
                                <button type="submit" class="btn btn-success pull-right">{{ __('admin/common.actions.save') }}</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
    @if($announcement && $canSave)
        <form action="{{ route('admin.design.announcements.view', $announcement->id) }}" method="POST">
            {!! csrf_field() !!}
            {!! method_field('DELETE') !!}
            <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash-o"></i> {{ __('admin/common.actions.delete') }}</button>
        </form>
    @endif
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(function () {
            function first(name) {
                var value = '';
                $('[data-preview="' + name + '"]').each(function () {
                    if (!value && this.value.trim()) value = this.value;
                });
                return value;
            }

            function update() {
                var type = $('#type').val();
                var button = first('button');
                $('#announcementPreview').attr('data-type', type);
                $('[data-icon-for]').hide().filter('[data-icon-for="' + type + '"]').show();
                $('#previewTitle').text(first('title') || @json(__('admin/announcements.preview_title')));
                $('#previewDescription').text(first('description') || @json(__('admin/announcements.preview_description')));
                $('#previewButton').text(button).toggle(button !== '');
                $('#previewClose').toggle($('#dismissible').is(':checked'));
                $('[data-scope]').hide().filter('[data-scope="' + $('#scope').val() + '"]').show();
            }

            $('#nodes').select2({ width: '100%' });
            $('#announcementForm').on('input change', update);
            update();
        });
    </script>
@endsection
