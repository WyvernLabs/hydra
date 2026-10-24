{{-- Hydra: settings of the SFTP backups addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="sftpBackupsUsers" name="users" value="1" @checked(old('users', (bool) $addon->get('users', true)))>
                    <label for="sftpBackupsUsers">{{ __('admin/addons.sftp_backups.users') }}</label>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/addons.sftp_backups.users_help') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{!! __('admin/addons.sftp_backups.nodes_hint', ['url' => route('admin.nodes')]) !!}</p>
            </div>
        </div>
    </div>
</div>
