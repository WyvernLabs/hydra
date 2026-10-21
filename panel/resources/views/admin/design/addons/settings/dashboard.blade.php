{{-- Hydra: settings of the server dashboard addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="dashboardLanding" name="landing" value="1" @checked(old('landing', $addon->landing()))>
                    <label for="dashboardLanding">{{ __('admin/addons.dashboard.landing') }}</label>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/addons.dashboard.landing_help') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small">{!! __('admin/addons.dashboard.banner_hint', ['url' => route('admin.nests')]) !!}</p>
                <p class="text-muted small no-margin">{!! __('admin/addons.dashboard.navigation_hint', ['url' => route('admin.design.navigation')]) !!}</p>
            </div>
        </div>
    </div>
</div>
