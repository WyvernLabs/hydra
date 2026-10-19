{{--
    Hydra: editor for one background with preview, upload, blur and darkening.
    Expects $prefix (field prefix), $background (image, blur, dim), $resetName,
    $resetValue and optionally $help and $resetLabel.
--}}
<div class="hydra-bg-editor" data-bg-editor>
    <div class="hydra-bg-preview" data-bg-preview>
        <div class="hydra-bg-preview-image" data-bg-image @if($background['image']) style="background-image: url('{{ $background['image'] }}')" data-has-image @endif></div>
        <div class="hydra-bg-preview-dim" data-bg-dim></div>
        <span class="hydra-bg-preview-label">{{ $background['image'] ? __('admin/design.backgrounds.custom') : __('admin/design.backgrounds.default') }}</span>
    </div>
    <div class="form-group">
        <label class="control-label">{{ __('admin/design.backgrounds.image') }}</label>
        <input type="file" name="{{ $prefix }}_image" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp" data-bg-file>
        <p class="text-muted"><small>{{ $help ?? __('admin/design.backgrounds.image_help') }}</small></p>
    </div>
    <div class="row">
        <div class="form-group col-sm-6">
            <label class="control-label">{{ __('admin/design.backgrounds.blur') }} <span class="text-muted" data-bg-blur-value>{{ $background['blur'] }} px</span></label>
            <input type="range" name="{{ $prefix }}_blur" min="0" max="{{ \Pterodactyl\Services\Hydra\BackgroundService::MAX_BLUR }}" value="{{ $background['blur'] }}" data-bg-blur>
        </div>
        <div class="form-group col-sm-6">
            <label class="control-label">{{ __('admin/design.backgrounds.dim') }} <span class="text-muted" data-bg-dim-value>{{ $background['dim'] }} %</span></label>
            <input type="range" name="{{ $prefix }}_dim" min="0" max="{{ \Pterodactyl\Services\Hydra\BackgroundService::MAX_DIM }}" value="{{ $background['dim'] }}" data-bg-dim-input>
        </div>
    </div>
    <button type="submit" name="{{ $resetName }}" value="{{ $resetValue }}" class="btn btn-sm btn-default" formnovalidate>
        <i class="fa fa-undo"></i> {{ $resetLabel ?? __('admin/design.backgrounds.reset') }}
    </button>
</div>
