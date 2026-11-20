{{-- Hydra: settings of the mod manager addon. Expects $addon. --}}
@php
    $nests = \Pterodactyl\Models\Nest::query()->with(['eggs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();
    $selected = old('eggs', $addon->eggs() ?? []);
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <label class="control-label">{{ __('admin/addons.mods.sources') }}</label>
                <div class="checkbox checkbox-primary" style="margin-top: 0;">
                    <input type="checkbox" id="modsModrinth" checked disabled>
                    <label for="modsModrinth">{{ __('admin/addons.mods.modrinth') }}</label>
                </div>
                <div class="checkbox checkbox-primary no-margin-bottom">
                    <input type="checkbox" id="modsCurseforge" name="curseforge" value="1" @checked(old('curseforge', $addon->get('curseforge', false)))>
                    <label for="modsCurseforge">{{ __('admin/addons.mods.curseforge') }}</label>
                </div>
                <p class="text-muted small" style="margin: 0 0 10px 20px;">{{ __('admin/addons.mods.curseforge_help') }}</p>
                <div class="form-group" style="margin-left: 20px;">
                    <label class="control-label" for="modsCurseforgeKey">{{ __('admin/addons.mods.curseforge_key') }}</label>
                    <input type="password" id="modsCurseforgeKey" name="curseforge_key" class="form-control" autocomplete="new-password"
                           @if(!empty($addon->get('curseforge_key'))) placeholder="{{ __('admin/addons.mods.key_set') }}" @endif>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{!! __('admin/addons.mods.curseforge_key_help', ['url' => 'https://console.curseforge.com/']) !!}</p>
                    @php($keyStatus = $addon->keyStatus())
                    @if(!empty($addon->get('curseforge_key')) && !is_null($keyStatus))
                        @if($keyStatus['status'] === 0 || $keyStatus['search'] === 0)
                            <p class="text-warning small no-margin" style="margin-top: 6px;">{{ __('admin/addons.mods.key_unreachable') }}</p>
                        @elseif($keyStatus['status'] < 200 || $keyStatus['status'] >= 300)
                            <p class="text-danger small no-margin" style="margin-top: 6px;">{{ __('admin/addons.mods.key_refused', ['status' => $keyStatus['status']]) }}</p>
                        @elseif($keyStatus['search'] < 200 || $keyStatus['search'] >= 300)
                            <p class="text-warning small no-margin" style="margin-top: 6px;">{{ __('admin/addons.mods.key_no_search', ['status' => $keyStatus['search']]) }}</p>
                        @else
                            <p class="text-success small no-margin" style="margin-top: 6px;">{{ __('admin/addons.mods.key_ok') }}</p>
                        @endif
                    @endif
                </div>
                <div class="checkbox checkbox-primary no-margin-bottom">
                    <input type="checkbox" id="modsDependencies" name="dependencies" value="1" @checked(old('dependencies', $addon->get('dependencies', true)))>
                    <label for="modsDependencies">{{ __('admin/addons.mods.dependencies') }}</label>
                </div>
                <p class="text-muted small" style="margin: 0 0 16px 20px;">{{ __('admin/addons.mods.dependencies_help', ['count' => \Pterodactyl\Services\Hydra\Minecraft\Mods\ModManagerService::MAX_DEPENDENCIES]) }}</p>
                <div class="checkbox checkbox-primary no-margin-bottom">
                    <input type="checkbox" id="modsHide" name="hide_without_folder" value="1" @checked(old('hide_without_folder', $addon->hidesWithoutFolder()))>
                    <label for="modsHide">{{ __('admin/addons.mods.hide_without_folder') }}</label>
                </div>
                <p class="text-muted small" style="margin: 0 0 16px 20px;">{{ __('admin/addons.mods.hide_without_folder_help') }}</p>
                <div class="form-group no-margin">
                    <label for="modsEggs" class="control-label">{{ __('admin/addons.mods.eggs') }}</label>
                    <select id="modsEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, array_map('intval', $selected), true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{!! __('admin/addons.mods.eggs_help', ['url' => route('admin.design.navigation')]) !!}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.mods.notice') }}</p>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        $('#modsEggs').select2({ width: '100%' });
    </script>
@endsection
