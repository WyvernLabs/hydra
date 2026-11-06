{{-- Hydra: settings of the Discord addon. Expects $addon. --}}
@php
    $roleMap = $addon->roleMap();
    $nodeMap = $addon->nodeMap();
    $panelRoles = \Pterodactyl\Models\AdminRole::query()->orderBy('sort')->orderBy('name')->get();
    $nodes = \Pterodactyl\Models\Node::query()->orderBy('name')->get();
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.discord.bot') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label class="control-label" for="discordToken">{{ __('admin/addons.discord.bot_token') }}</label>
                    <input type="password" id="discordToken" name="bot_token" class="form-control" autocomplete="new-password"
                           @if(!blank($addon->get('bot_token'))) placeholder="{{ __('admin/addons.discord.bot_token_set') }}" @endif>
                </div>
                <div class="form-group">
                    <label class="control-label" for="discordGuild">{{ __('admin/addons.discord.guild_id') }}</label>
                    <input type="text" id="discordGuild" name="guild_id" class="form-control" value="{{ old('guild_id', $addon->guild()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.discord.guild_id_help') }}</p>
                </div>
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="discordTickets" name="ticket_notifications" value="1" @checked(old('ticket_notifications', $addon->notifiesTickets()))>
                    <label for="discordTickets">{{ __('admin/addons.discord.ticket_notifications') }}</label>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/addons.discord.ticket_notifications_help') }}</p>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.discord.roles') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">{{ __('admin/addons.discord.roles_help') }}</p>
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label class="control-label" for="discordVerified">{{ __('admin/addons.discord.role_verified') }}</label>
                        <input type="text" id="discordVerified" name="role_verified" class="form-control" value="{{ old('role_verified', $addon->get('role_verified')) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.discord.role_verified_help') }}</p>
                    </div>
                    <div class="form-group col-sm-6">
                        <label class="control-label" for="discordServer">{{ __('admin/addons.discord.role_server') }}</label>
                        <input type="text" id="discordServer" name="role_server" class="form-control" value="{{ old('role_server', $addon->get('role_server')) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.discord.role_server_help') }}</p>
                    </div>
                </div>

                <h4>{{ __('admin/addons.discord.panel_roles') }}</h4>
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label class="control-label" for="discordRoot">{{ __('admin/addons.discord.root_admin') }}</label>
                        <input type="text" id="discordRoot" name="role_map[root]" class="form-control" value="{{ old('role_map.root', $roleMap['root'] ?? '') }}">
                    </div>
                    @foreach($panelRoles as $role)
                        <div class="form-group col-sm-6">
                            <label class="control-label" for="discordRole{{ $role->id }}">{{ $role->name }}</label>
                            <input type="text" id="discordRole{{ $role->id }}" name="role_map[{{ $role->id }}]" class="form-control" value="{{ old('role_map.' . $role->id, $roleMap[$role->id] ?? '') }}">
                        </div>
                    @endforeach
                </div>

                <h4>{{ __('admin/addons.discord.node_roles') }}</h4>
                <p class="text-muted small">{{ __('admin/addons.discord.node_roles_help') }}</p>
                <div class="row">
                    @foreach($nodes as $node)
                        <div class="form-group col-sm-6">
                            <label class="control-label" for="discordNode{{ $node->id }}">{{ $node->name }}</label>
                            <input type="text" id="discordNode{{ $node->id }}" name="node_map[{{ $node->id }}]" class="form-control" value="{{ old('node_map.' . $node->id, $nodeMap[$node->id] ?? '') }}">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p>{{ __('admin/addons.discord.setup_title') }}</p>
                <ol class="small" style="padding-left: 18px;">
                    <li>{{ __('admin/addons.discord.setup_1') }}</li>
                    <li>{{ __('admin/addons.discord.setup_2') }}</li>
                    <li>{{ __('admin/addons.discord.setup_3') }}</li>
                    <li>{{ __('admin/addons.discord.setup_4') }}</li>
                </ol>
                @if(!is_null($addon->missing()))
                    <p class="text-muted small no-margin">{{ __('admin/addons.discord.test_hint') }}</p>
                @else
                    <a href="{{ route('admin.design.discord.test') }}" class="btn btn-sm btn-primary">{{ __('admin/addons.discord.test_button') }}</a>
                    <p class="text-muted small" style="margin: 10px 0 0;">{{ __('admin/addons.discord.sync_hint') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
