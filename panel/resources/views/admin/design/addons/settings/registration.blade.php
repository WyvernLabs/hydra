{{-- Hydra: settings of the registration addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label class="control-label" for="registrationLifetime">{{ __('admin/addons.registration.code_lifetime') }}</label>
                    <div class="input-group" style="max-width: 220px;">
                        <input type="number" id="registrationLifetime" name="code_lifetime" class="form-control" min="5" max="60" required
                               value="{{ old('code_lifetime', $addon->codeLifetime()) }}">
                        <span class="input-group-addon">{{ __('admin/addons.registration.minutes') }}</span>
                    </div>
                    <p class="text-muted small">{{ __('admin/addons.registration.code_lifetime_help') }}</p>
                </div>
                <div class="form-group no-margin-bottom">
                    <label class="control-label" for="registrationDomains">{{ __('admin/addons.registration.allowed_domains') }}</label>
                    <textarea id="registrationDomains" name="allowed_domains" class="form-control" rows="3">{{ old('allowed_domains', implode(', ', $addon->allowedDomains())) }}</textarea>
                    <p class="text-muted small">{!! __('admin/addons.registration.allowed_domains_help') !!}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small">{!! __('admin/addons.registration.mail_hint', ['url' => route('admin.settings.mail')]) !!}</p>
                <p class="text-muted small no-margin">{!! __('admin/addons.registration.turnstile_hint', ['url' => route('admin.design.addons.view', 'turnstile')]) !!}</p>
            </div>
        </div>
    </div>
</div>
