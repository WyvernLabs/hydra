{{-- Hydra: settings of the status page addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label for="statusTitle" class="control-label">{{ __('admin/addons.status.page_title') }}</label>
                    <input type="text" id="statusTitle" name="title" maxlength="64" class="form-control" value="{{ old('title', $addon->get('title')) }}" placeholder="{{ __('client/status.title') }}">
                </div>
                <div class="form-group">
                    <label for="statusDescription" class="control-label">{{ __('admin/addons.status.page_description') }}</label>
                    <textarea id="statusDescription" name="description" rows="2" maxlength="500" class="form-control">{{ old('description', $addon->get('description')) }}</textarea>
                </div>
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="statusPrivateNodes" name="private_nodes" value="1" @checked(old('private_nodes', $addon->showsPrivateNodes()))>
                    <label for="statusPrivateNodes">{{ __('admin/addons.status.private_nodes') }}</label>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/addons.status.private_nodes_help') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p>{{ __('admin/addons.status.hint') }}</p>
                <a href="{{ route('admin.design.status') }}" class="btn btn-sm btn-primary">{{ __('admin/addons.status.manage') }}</a>
                @if($addon->enabled())
                    <a href="{{ route('status') }}" target="_blank" class="btn btn-sm btn-default">/status</a>
                @endif
                <p class="text-muted small" style="margin: 10px 0 0;">{{ __('admin/addons.status.schedule_hint') }}</p>
            </div>
        </div>
    </div>
</div>
