{{-- Hydra: settings of the autodelete addon. Expects $addon. --}}
@php($due = app(\Pterodactyl\Services\Hydra\AutoDeleteService::class))
<div class="row">
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.autodelete.servers_title') }}</h3>
            </div>
            <div class="box-body">
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="autodeleteServers" name="servers" value="1" @checked(old('servers', $addon->get('servers', true)))>
                    <label for="autodeleteServers">{{ __('admin/addons.autodelete.servers') }}</label>
                </div>
                <div class="form-group" style="margin-top: 10px;">
                    <label class="control-label" for="autodeleteServerDays">{{ __('admin/addons.autodelete.after') }}</label>
                    <div class="input-group" style="max-width: 220px;">
                        <input type="number" id="autodeleteServerDays" name="server_days" class="form-control" min="1" max="3650" required value="{{ old('server_days', $addon->serverDays()) }}">
                        <span class="input-group-addon">{{ __('admin/addons.autodelete.days') }}</span>
                    </div>
                    <p class="text-muted small">{{ __('admin/addons.autodelete.servers_help') }}</p>
                </div>
                <p class="small no-margin">{{ __('admin/addons.autodelete.due_servers', ['count' => $due->dueServers()->count()]) }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.autodelete.users_title') }}</h3>
            </div>
            <div class="box-body">
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="autodeleteUsers" name="users" value="1" @checked(old('users', $addon->get('users', true)))>
                    <label for="autodeleteUsers">{{ __('admin/addons.autodelete.users') }}</label>
                </div>
                <div class="form-group" style="margin-top: 10px;">
                    <label class="control-label" for="autodeleteUserDays">{{ __('admin/addons.autodelete.after') }}</label>
                    <div class="input-group" style="max-width: 220px;">
                        <input type="number" id="autodeleteUserDays" name="user_days" class="form-control" min="1" max="3650" required value="{{ old('user_days', $addon->userDays()) }}">
                        <span class="input-group-addon">{{ __('admin/addons.autodelete.days') }}</span>
                    </div>
                    <p class="text-muted small">{{ __('admin/addons.autodelete.users_help') }}</p>
                </div>
                <p class="small no-margin">{{ __('admin/addons.autodelete.due_users', ['count' => $due->dueUsers()->count()]) }}</p>
            </div>
        </div>
    </div>
    <div class="col-xs-12">
        <div class="callout callout-warning">
            <p class="small no-margin">{{ __('admin/addons.autodelete.warning') }}</p>
        </div>
    </div>
</div>
