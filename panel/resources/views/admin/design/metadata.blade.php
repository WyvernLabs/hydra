@extends('layouts.admin')
@include('partials/admin.design.nav', ['activeTab' => 'metadata'])

@section('title')
    {{ __('admin/design.metadata.title') }}
@endsection

@section('content-header')
    <h1>{{ __('admin/design.metadata.title') }}<small>{{ __('admin/design.metadata.subtitle') }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">{{ __('admin/common.admin') }}</a></li>
        <li><a href="{{ route('admin.design') }}">{{ __('admin/design.title') }}</a></li>
        <li class="active">{{ __('admin/design.tabs.metadata') }}</li>
    </ol>
@endsection

@section('content')
    @yield('design::nav')
    <form action="{{ route('admin.design.metadata') }}" method="POST" enctype="multipart/form-data" id="hydraMeta">
        <div class="row">
            <div class="col-md-7">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.metadata.texts') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label" for="metaTitle">{{ __('admin/design.metadata.title_field') }}</label>
                            <input type="text" id="metaTitle" name="title" class="form-control" maxlength="{{ \Pterodactyl\Services\Hydra\MetadataService::MAX_TITLE }}"
                                   value="{{ old('title', $meta['title']) }}" placeholder="{{ config('app.name', 'Hydra') }}" data-meta="title">
                            <p class="text-muted"><small>{{ __('admin/design.metadata.title_help') }}</small></p>
                        </div>
                        <div class="form-group">
                            <label class="control-label" for="metaDescription">
                                {{ __('admin/design.metadata.description') }}
                                <span class="text-muted"><span data-meta-count>0</span> / {{ \Pterodactyl\Services\Hydra\MetadataService::MAX_DESCRIPTION }}</span>
                            </label>
                            <textarea id="metaDescription" name="description" class="form-control" rows="3" maxlength="{{ \Pterodactyl\Services\Hydra\MetadataService::MAX_DESCRIPTION }}"
                                      placeholder="{{ __('admin/design.metadata.description_placeholder') }}" data-meta="description">{{ old('description', $meta['description']) }}</textarea>
                            <p class="text-muted"><small>{{ __('admin/design.metadata.description_help') }}</small></p>
                        </div>
                        <div class="form-group">
                            <label class="control-label" for="metaKeywords">{{ __('admin/design.metadata.keywords') }}</label>
                            <input type="text" id="metaKeywords" name="keywords" class="form-control" maxlength="{{ \Pterodactyl\Services\Hydra\MetadataService::MAX_KEYWORDS }}"
                                   value="{{ old('keywords', $meta['keywords']) }}" placeholder="{{ __('admin/design.metadata.keywords_placeholder') }}">
                            <p class="text-muted"><small>{{ __('admin/design.metadata.keywords_help') }}</small></p>
                        </div>
                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input id="metaUseColor" name="use_color" type="checkbox" value="1" @if(old('use_color', $meta['color'] !== null)) checked @endif data-meta="use_color">
                                <label for="metaUseColor">{{ __('admin/design.metadata.color') }}</label>
                            </div>
                            <div class="hydra-color-field">
                                <input type="color" value="{{ old('color', $meta['color'] ?? $branding->accent()) }}" data-meta-picker>
                                <input type="text" class="form-control" name="color" value="{{ old('color', $meta['color'] ?? $branding->accent()) }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" data-meta="color">
                            </div>
                            <p class="text-muted"><small>{{ __('admin/design.metadata.color_help') }}</small></p>
                        </div>
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input id="metaIndexable" name="indexable" type="checkbox" value="1" @if(old('indexable', $meta['indexable'])) checked @endif>
                            <label for="metaIndexable">{{ __('admin/design.metadata.indexable') }}</label>
                        </div>
                        <p class="text-muted"><small>{{ __('admin/design.metadata.indexable_help') }}</small></p>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.metadata.image') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <input type="file" name="image" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp" data-meta-file>
                            <p class="text-muted"><small>{{ __('admin/design.metadata.image_help') }}</small></p>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input id="metaLarge" name="large_image" type="checkbox" value="1" @if(old('large_image', $meta['large_image'])) checked @endif data-meta="large_image">
                            <label for="metaLarge">{{ __('admin/design.metadata.large_image') }}</label>
                        </div>
                        @if($imageUrl)
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input id="metaResetImage" name="reset_image" type="checkbox" value="1" data-meta="reset_image">
                                <label for="metaResetImage">{{ __('admin/design.metadata.image_reset') }}</label>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ __('admin/design.metadata.preview') }}</h3>
                    </div>
                    <div class="box-body">
                        <div class="hydra-embed" data-meta-embed style="--embed-color: {{ $meta['color'] ?? $branding->accent() }}">
                            <div class="hydra-embed-body">
                                <div class="hydra-embed-site">{{ config('app.name', 'Hydra') }}</div>
                                <div class="hydra-embed-title" data-meta-preview="title"></div>
                                <div class="hydra-embed-description" data-meta-preview="description"></div>
                                <img class="hydra-embed-image" alt="" data-meta-preview="image" @if($imageUrl) src="{{ $imageUrl }}" @endif>
                            </div>
                        </div>
                        <p class="text-muted no-margin"><small>{{ __('admin/design.metadata.preview_help') }}</small></p>
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
                        <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        // Live preview of the link card, roughly as Discord shows it.
        (function () {
            var form = document.getElementById('hydraMeta');
            var embed = form.querySelector('[data-meta-embed]');
            var image = form.querySelector('[data-meta-preview="image"]');
            var picker = form.querySelector('[data-meta-picker]');
            var color = form.querySelector('[data-meta="color"]');
            var useColor = form.querySelector('[data-meta="use_color"]');
            var reset = form.querySelector('[data-meta="reset_image"]');
            var savedImage = image.getAttribute('src');
            var fallbackColor = @json($branding->accent());
            var fallbackTitle = @json(config('app.name', 'Hydra'));
            var valid = /^#[0-9a-fA-F]{6}$/;

            function field(name) { return form.querySelector('[data-meta="' + name + '"]'); }

            function update() {
                var title = field('title').value.trim() || fallbackTitle;
                var description = field('description').value.trim();
                form.querySelector('[data-meta-preview="title"]').textContent = title;
                var text = form.querySelector('[data-meta-preview="description"]');
                text.textContent = description;
                text.hidden = description === '';
                form.querySelector('[data-meta-count]').textContent = field('description').value.length;

                var value = color.value.trim();
                if (valid.test(value)) picker.value = value;
                color.disabled = picker.disabled = !useColor.checked;
                embed.style.setProperty('--embed-color', useColor.checked && valid.test(value) ? value : fallbackColor);

                var hasImage = !!image.getAttribute('src') && !(reset && reset.checked && image.getAttribute('src') === savedImage);
                image.hidden = !hasImage;
                embed.classList.toggle('is-small-image', !field('large_image').checked);
            }

            picker.addEventListener('input', function () { color.value = picker.value; update(); });
            form.querySelector('[data-meta-file]').addEventListener('change', function (event) {
                var file = event.target.files[0];
                if (file) {
                    image.src = URL.createObjectURL(file);
                    if (reset) reset.checked = false;
                } else if (savedImage) {
                    image.src = savedImage;
                } else {
                    image.removeAttribute('src');
                }
                update();
            });
            form.querySelectorAll('[data-meta]').forEach(function (input) {
                input.addEventListener('input', update);
                input.addEventListener('change', update);
            });
            update();
        })();
    </script>
@endsection
