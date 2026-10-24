{{-- Hydra: settings of the resource pool addon. Expects $addon. --}}
@php
    $nests = \Pterodactyl\Models\Nest::query()->with(['eggs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();
    $locations = \Pterodactyl\Models\Location::query()->orderBy('short')->get();
    $selectedEggs = array_map('intval', old('eggs', $addon->eggIds()));
    $selectedLocations = array_map('intval', old('locations', $addon->locationIds()));
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label for="poolEggs" class="control-label">{{ __('admin/addons.resource_pool.eggs') }}</label>
                    <select id="poolEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, $selectedEggs, true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{{ __('admin/addons.resource_pool.eggs_help') }}</p>
                </div>
                <div class="form-group">
                    <label for="poolLocations" class="control-label">{{ __('admin/addons.resource_pool.locations') }}</label>
                    <select id="poolLocations" name="locations[]" class="form-control" multiple>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(in_array($location->id, $selectedLocations, true))>{{ $location->short }}{{ $location->long ? ' – ' . $location->long : '' }}</option>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{{ __('admin/addons.resource_pool.locations_help') }}</p>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="poolUserEdit" name="user_edit" value="1" @checked(old('user_edit', $addon->userMayEdit()))>
                    <label for="poolUserEdit">{{ __('admin/addons.resource_pool.user_edit') }}</label>
                </div>
                <div class="checkbox checkbox-primary no-margin-bottom">
                    <input type="checkbox" id="poolUserDelete" name="user_delete" value="1" @checked(old('user_delete', $addon->userMayDelete()))>
                    <label for="poolUserDelete">{{ __('admin/addons.resource_pool.user_delete') }}</label>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.resource_pool.notice') }}</p>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        $('#poolEggs, #poolLocations').select2({ width: '100%' });
    </script>
@endsection
