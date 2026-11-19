{{-- Hydra: settings of the privacy blur addon. Expects $addon. --}}
@php($range = \Pterodactyl\Services\Hydra\Addons\PrivacyBlurAddon::STRENGTH)
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                @foreach(['ips', 'emails', 'admin'] as $option)
                    <div class="checkbox checkbox-primary no-margin-bottom">
                        <input type="checkbox" id="privacy_{{ $option }}" name="{{ $option }}" value="1" @checked(old($option, $addon->get($option, true)))>
                        <label for="privacy_{{ $option }}">{{ __("admin/addons.privacy_blur.$option") }}</label>
                    </div>
                    <p class="text-muted small" style="margin: 0 0 12px 20px;">{{ __("admin/addons.privacy_blur.{$option}_help") }}</p>
                @endforeach
                <div class="form-group no-margin" style="max-width: 260px;">
                    <label for="privacyStrength" class="control-label">{{ __('admin/addons.privacy_blur.strength') }}</label>
                    <input type="number" id="privacyStrength" name="strength" min="{{ $range[0] }}" max="{{ $range[1] }}" class="form-control" value="{{ old('strength', $addon->strength()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.privacy_blur.strength_help', ['min' => $range[0], 'max' => $range[1]]) }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p style="margin: 0 0 10px;">
                    <span class="hydra-privacy-demo" style="filter: blur({{ $addon->strength() }}px); transition: filter .15s;" onmouseenter="this.style.filter='none'" onmouseleave="this.style.filter='blur({{ $addon->strength() }}px)'">203.0.113.42:25565</span>
                </p>
                <p class="text-muted small no-margin">{{ __('admin/addons.privacy_blur.notice') }}</p>
            </div>
        </div>
    </div>
</div>
