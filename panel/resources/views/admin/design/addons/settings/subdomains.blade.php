{{-- Hydra: settings of the subdomains addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label for="subdomainsLimit" class="control-label">{{ __('admin/addons.subdomains.default_limit') }}</label>
                    <input type="number" id="subdomainsLimit" name="default_limit" min="0" max="100" class="form-control" value="{{ old('default_limit', $addon->defaultLimit()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.subdomains.default_limit_help') }}</p>
                </div>
                <div class="form-group no-margin">
                    <label for="subdomainsBlocked" class="control-label">{{ __('admin/addons.subdomains.blocked') }}</label>
                    <textarea id="subdomainsBlocked" name="blocked" rows="3" class="form-control" placeholder="shop, status, play">{{ old('blocked', $addon->get('blocked', '')) }}</textarea>
                    <p class="text-muted small no-margin">{{ __('admin/addons.subdomains.blocked_help', ['always' => implode(', ', \Pterodactyl\Services\Hydra\Addons\SubdomainsAddon::ALWAYS_BLOCKED)]) }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p>{{ __('admin/addons.subdomains.domains_hint') }}</p>
                <a href="{{ route('admin.design.domains') }}" class="btn btn-sm btn-primary"><i class="fa fa-globe"></i> {{ __('admin/addons.subdomains.manage_domains') }}</a>
                <p class="text-muted small" style="margin: 10px 0 0;">{{ __('admin/addons.subdomains.limit_hint') }}</p>
            </div>
        </div>
    </div>
</div>
