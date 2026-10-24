{{-- Hydra: settings of the trash addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group no-margin">
                    <label for="trashDays" class="control-label">{{ __('admin/addons.trash.days') }}</label>
                    <input type="number" id="trashDays" name="days" min="1" max="365" class="form-control" value="{{ old('days', $addon->days()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.trash.days_help') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.trash.notice') }}</p>
            </div>
        </div>
    </div>
</div>
