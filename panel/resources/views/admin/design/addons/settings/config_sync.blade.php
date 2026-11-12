{{-- Hydra: settings of the config sync addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="form-group col-sm-6 no-margin">
                        <label for="configSyncEntries" class="control-label">{{ __('admin/addons.config_sync.max_entries') }}</label>
                        <input type="number" id="configSyncEntries" name="max_entries" min="1" max="200" class="form-control" value="{{ old('max_entries', $addon->maxEntries()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.config_sync.max_entries_help') }}</p>
                    </div>
                    <div class="form-group col-sm-6 no-margin">
                        <label for="configSyncSize" class="control-label">{{ __('admin/addons.config_sync.max_size') }}</label>
                        <input type="number" id="configSyncSize" name="max_size" min="16" max="4096" class="form-control" value="{{ old('max_size', $addon->maxSize()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.config_sync.max_size_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.config_sync.notice') }}</p>
            </div>
        </div>
    </div>
</div>
