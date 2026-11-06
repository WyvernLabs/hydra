{{-- Hydra: settings of the server templates addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-body">
                <p>{{ __('admin/addons.server_templates.hint') }}</p>
                @if($addon->enabled())
                    @if(Auth::user()->hasAdminPermission('servers.templates'))
                        <a href="{{ route('admin.servers.templates') }}" class="btn btn-sm btn-primary">{{ __('admin/addons.server_templates.manage') }}</a>
                    @endif
                @else
                    <p class="text-muted small no-margin">{{ __('admin/addons.server_templates.enable_first') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
