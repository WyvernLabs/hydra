{{-- Hydra: settings of the installable app (addon "pwa"). Expects $addon. --}}
@php
    $pwaService = app(\Pterodactyl\Services\Hydra\PwaService::class);
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="form-group col-sm-8">
                        <label for="pwaName" class="control-label">{{ __('admin/addons.pwa.name_label') }}</label>
                        <input type="text" id="pwaName" name="name" maxlength="60" class="form-control" value="{{ old('name', $addon->get('name')) }}" placeholder="{{ config('app.name') }}">
                    </div>
                    <div class="form-group col-sm-4">
                        <label for="pwaShortName" class="control-label">{{ __('admin/addons.pwa.short_name') }}</label>
                        <input type="text" id="pwaShortName" name="short_name" maxlength="20" class="form-control" value="{{ old('short_name', $addon->get('short_name')) }}" placeholder="{{ $addon->shortName() }}">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label for="pwaTheme" class="control-label">{{ __('admin/addons.pwa.theme_color') }}</label>
                        <input type="color" id="pwaTheme" name="theme_color" class="form-control" value="{{ old('theme_color', $addon->themeColor()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.pwa.theme_color_help') }}</p>
                    </div>
                    <div class="form-group col-sm-6">
                        <label for="pwaBackground" class="control-label">{{ __('admin/addons.pwa.background_color') }}</label>
                        <input type="color" id="pwaBackground" name="background_color" class="form-control" value="{{ old('background_color', $addon->backgroundColor()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.pwa.background_color_help') }}</p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="pwaIcon" class="control-label">{{ __('admin/addons.pwa.icon') }}</label>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <img src="/pwa/icon-192.png?v={{ $pwaService->version() }}" alt="" width="64" height="64" style="border-radius: 14px; background: #2b2b2b;">
                        <div style="flex: 1;">
                            <input type="file" id="pwaIcon" name="icon_upload" accept="image/png,image/jpeg,image/webp" class="form-control">
                            @if($pwaService->hasIcon())
                                <div class="checkbox checkbox-primary" style="margin-bottom: 0;">
                                    <input type="checkbox" id="pwaIconRemove" name="icon_remove" value="1">
                                    <label for="pwaIconRemove">{{ __('admin/addons.pwa.icon_remove') }}</label>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{{ __('admin/addons.pwa.icon_help') }}</p>
                </div>
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="pwaTabs" name="tabs" value="1" @checked(old('tabs', $addon->tabs()))>
                    <label for="pwaTabs">{{ __('admin/addons.pwa.tabs') }}</label>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/addons.pwa.tabs_help') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.pwa.notice') }}</p>
            </div>
        </div>
    </div>
</div>
