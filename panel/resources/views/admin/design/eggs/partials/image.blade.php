{{-- Hydra: one picture of an egg (banner or server list). Expects $title, $help, $format, $image, $resetLabel and $height. --}}
@php($canSave = Auth::user()->hasAdminPermission('design.update'))
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">{{ $title }}</h3>
    </div>
    <div class="box-body">
        <p class="text-muted small">{{ $help }}</p>
        @if($image)
            <img src="{{ $image }}" alt="" style="display: block; width: 100%; height: {{ $height }}; object-fit: cover; border-radius: 4px; margin-bottom: 10px;">
        @endif
        <input type="file" name="image_image" accept="image/png,image/jpeg,image/gif,image/webp" class="form-control" @disabled(!$canSave)>
        <p class="text-muted small no-margin" style="margin-top: 6px;">{{ $format }}</p>
    </div>
    @if($canSave)
        <div class="box-footer">
            @if($image)
                <button type="submit" name="reset" value="1" class="btn btn-sm btn-danger">{{ $resetLabel }}</button>
            @endif
            <button type="submit" class="btn btn-sm btn-primary pull-right">{{ __('admin/common.actions.save') }}</button>
        </div>
    @endif
</div>
