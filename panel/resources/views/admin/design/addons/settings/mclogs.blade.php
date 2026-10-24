{{-- Hydra: settings of the mclo.gs addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group no-margin">
                    <label for="mclogsPath" class="control-label">{{ __('admin/addons.mclogs.path') }}</label>
                    <input type="text" id="mclogsPath" name="path" maxlength="191" class="form-control" value="{{ old('path', $addon->path()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.mclogs.path_help') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.mclogs.notice') }}</p>
            </div>
        </div>
    </div>
</div>
