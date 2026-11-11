{{-- Hydra: settings of the knowledgebase addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label for="kbPath" class="control-label">{{ __('admin/addons.knowledgebase.path') }}</label>
                    <div class="input-group">
                        <span class="input-group-addon">{{ rtrim(config('app.url'), '/') }}/</span>
                        <input type="text" id="kbPath" name="path" maxlength="40" class="form-control" value="{{ old('path', $addon->path()) }}" pattern="[a-z0-9]+(-[a-z0-9]+)*" required>
                    </div>
                    <p class="text-muted small">{{ __('admin/addons.knowledgebase.path_help') }}</p>
                </div>
                <div class="form-group">
                    <label for="kbUploadLimit" class="control-label">{{ __('admin/addons.knowledgebase.upload_limit') }}</label>
                    <input type="number" id="kbUploadLimit" name="upload_limit" min="1" max="500" class="form-control" value="{{ old('upload_limit', $addon->uploadLimit()) }}" required>
                </div>
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="kbPublic" name="public" value="1" @checked(old('public', $addon->isPublic()))>
                    <label for="kbPublic">{{ __('admin/addons.knowledgebase.public') }}</label>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/addons.knowledgebase.public_help') }}</p>
            </div>
        </div>
    </div>
    @if($addon->enabled())
        <div class="col-md-4">
            <div class="box">
                <div class="box-body">
                    @if(Auth::user()->hasAdminPermission('knowledgebase.view'))
                        <a href="{{ route('admin.knowledgebase') }}" class="btn btn-sm btn-primary">{{ __('admin/addons.knowledgebase.manage') }}</a>
                    @endif
                    <a href="{{ $addon->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-default">{{ __('admin/addons.knowledgebase.open') }}</a>
                </div>
            </div>
        </div>
    @endif
</div>
