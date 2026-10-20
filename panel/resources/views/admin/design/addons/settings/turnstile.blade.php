{{-- Hydra: settings of the Cloudflare Turnstile addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.turnstile.keys') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">{!! __('admin/addons.turnstile.keys_help', ['url' => 'https://dash.cloudflare.com/?to=/:account/turnstile']) !!}</p>
                <div class="form-group">
                    <label class="control-label" for="turnstileSiteKey">{{ __('admin/addons.turnstile.site_key') }}</label>
                    <input type="text" id="turnstileSiteKey" name="site_key" class="form-control" autocomplete="off" value="{{ old('site_key', $addon->get('site_key')) }}">
                </div>
                <div class="form-group no-margin-bottom">
                    <label class="control-label" for="turnstileSecretKey">{{ __('admin/addons.turnstile.secret_key') }}</label>
                    <input type="password" id="turnstileSecretKey" name="secret_key" class="form-control" autocomplete="new-password"
                           @if(!empty($addon->get('secret_key'))) placeholder="{{ __('admin/addons.turnstile.secret_key_set') }}" @endif>
                    <p class="text-muted small">{{ __('admin/addons.turnstile.secret_key_help') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.turnstile.forms_title') }}</h3>
            </div>
            <div class="box-body">
                @foreach(\Pterodactyl\Services\Hydra\Addons\TurnstileAddon::FORMS as $form)
                    <div class="checkbox checkbox-primary">
                        <input type="hidden" name="{{ $form }}" value="0">
                        <input type="checkbox" id="turnstileForm{{ ucfirst($form) }}" name="{{ $form }}" value="1" @checked(old($form, $addon->get($form)))>
                        <label for="turnstileForm{{ ucfirst($form) }}">{{ __("admin/addons.turnstile.forms.$form") }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
