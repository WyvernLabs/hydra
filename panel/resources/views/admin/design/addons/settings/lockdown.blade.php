{{-- Hydra: the lockdown addon has no settings, only facts. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.lockdown.info_title') }}</h3>
            </div>
            <div class="box-body">
                <p>{{ __('admin/addons.lockdown.info') }}</p>
                <p class="no-margin"><strong>{{ __('admin/addons.lockdown.summary', ['count' => $addon->lockedDownCount()]) }}</strong></p>
                @unless($addon->enabled())
                    <p class="text-muted small" style="margin: 10px 0 0;">{{ __('admin/addons.lockdown.disabled_hint') }}</p>
                @endunless
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{!! __('admin/addons.lockdown.roles_hint', ['url' => route('admin.roles')]) !!}</p>
            </div>
        </div>
    </div>
</div>
