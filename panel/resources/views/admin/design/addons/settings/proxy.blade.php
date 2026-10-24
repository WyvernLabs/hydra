{{-- Hydra: settings of the reverse proxy addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group no-margin">
                    <label for="proxyLimit" class="control-label">{{ __('admin/addons.proxy.default_limit') }}</label>
                    <input type="number" id="proxyLimit" name="default_limit" min="0" max="100" class="form-control" value="{{ old('default_limit', $addon->defaultLimit()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.proxy.default_limit_help') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.proxy.notice') }}</p>
            </div>
        </div>
    </div>
</div>
