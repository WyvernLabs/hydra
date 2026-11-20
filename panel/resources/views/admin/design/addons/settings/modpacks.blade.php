{{-- Hydra: settings of the modpack installer addon. Expects $addon. --}}
@php
    $nests = \Pterodactyl\Models\Nest::query()->with(['eggs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();
    $selected = old('eggs', $addon->eggs() ?? []);
    $ownKey = !empty($addon->get('curseforge_key'));
    $modsKey = !empty(app(\Pterodactyl\Services\Hydra\Addons\ModsAddon::class)->get('curseforge_key'));
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <label class="control-label">{{ __('admin/addons.modpacks.sources') }}</label>
                <div class="checkbox checkbox-primary" style="margin-top: 0;">
                    <input type="checkbox" id="modpacksModrinth" checked disabled>
                    <label for="modpacksModrinth">{{ __('admin/addons.modpacks.modrinth') }}</label>
                </div>
                <div class="checkbox checkbox-primary no-margin-bottom">
                    <input type="checkbox" id="modpacksCurseforge" name="curseforge" value="1" @checked(old('curseforge', $addon->get('curseforge', false)))>
                    <label for="modpacksCurseforge">{{ __('admin/addons.modpacks.curseforge') }}</label>
                </div>
                <div class="form-group" style="margin: 10px 0 16px 20px;">
                    <label class="control-label" for="modpacksCurseforgeKey">{{ __('admin/addons.modpacks.curseforge_key') }}</label>
                    <input type="password" id="modpacksCurseforgeKey" name="curseforge_key" class="form-control" autocomplete="new-password"
                           @if($ownKey) placeholder="{{ __('admin/addons.modpacks.key_set') }}" @endif>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{!! __('admin/addons.modpacks.curseforge_key_help', ['url' => 'https://console.curseforge.com/']) !!}</p>
                    @if(!$ownKey && $modsKey)
                        <p class="text-success small no-margin" style="margin-top: 6px;">{{ __('admin/addons.modpacks.key_from_mods') }}</p>
                    @endif
                </div>
                <div class="form-group no-margin">
                    <label for="modpacksEggs" class="control-label">{{ __('admin/addons.modpacks.eggs') }}</label>
                    <select id="modpacksEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, array_map('intval', $selected), true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{!! __('admin/addons.modpacks.eggs_help', ['url' => route('admin.design.navigation')]) !!}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.modpacks.notice') }}</p>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        $('#modpacksEggs').select2({ width: '100%' });
    </script>
@endsection
