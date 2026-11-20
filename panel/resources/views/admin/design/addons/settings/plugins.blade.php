{{-- Hydra: settings of the plugin manager addon. Expects $addon. --}}
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
                <label class="control-label">{{ __('admin/addons.plugins.sources') }}</label>
                <div class="checkbox checkbox-primary" style="margin-top: 0;">
                    <input type="checkbox" id="pluginsModrinth" checked disabled>
                    <label for="pluginsModrinth">{{ __('admin/addons.plugins.modrinth') }}</label>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="pluginsHangar" name="hangar" value="1" @checked(old('hangar', $addon->get('hangar', true)))>
                    <label for="pluginsHangar">{{ __('admin/addons.plugins.hangar') }}</label>
                </div>
                <div class="checkbox checkbox-primary no-margin-bottom">
                    <input type="checkbox" id="pluginsSpigot" name="spigot" value="1" @checked(old('spigot', $addon->get('spigot', true)))>
                    <label for="pluginsSpigot">{{ __('admin/addons.plugins.spigot') }}</label>
                </div>
                <p class="text-muted small" style="margin: 0 0 16px 20px;">{{ __('admin/addons.plugins.spigot_help') }}</p>
                <div class="checkbox checkbox-primary no-margin-bottom">
                    <input type="checkbox" id="pluginsHide" name="hide_without_folder" value="1" @checked(old('hide_without_folder', $addon->hidesWithoutFolder()))>
                    <label for="pluginsHide">{{ __('admin/addons.plugins.hide_without_folder') }}</label>
                </div>
                <p class="text-muted small" style="margin: 0 0 16px 20px;">{{ __('admin/addons.plugins.hide_without_folder_help') }}</p>
                <div class="form-group no-margin">
                    <label for="pluginsEggs" class="control-label">{{ __('admin/addons.plugins.eggs') }}</label>
                    <select id="pluginsEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, array_map('intval', $selected), true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{!! __('admin/addons.plugins.eggs_help', ['url' => route('admin.design.navigation')]) !!}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.plugins.notice') }}</p>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        $('#pluginsEggs').select2({ width: '100%' });
    </script>
@endsection
