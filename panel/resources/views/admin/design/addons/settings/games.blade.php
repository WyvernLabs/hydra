{{-- Hydra: settings of the game library addon. Expects $addon. --}}
@php($offered = old('eggs', $addon->eggIds()))
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.games.eggs') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">{{ __('admin/addons.games.eggs_help') }}</p>
                @foreach(\Pterodactyl\Models\Nest::query()->with('eggs')->orderBy('name')->get() as $nest)
                    @continue($nest->eggs->isEmpty())
                    <p class="no-margin" style="margin-top: 10px;"><strong>{{ $nest->name }}</strong></p>
                    <div class="row">
                        @foreach($nest->eggs->sortBy('name') as $egg)
                            <div class="col-sm-6">
                                <div class="checkbox checkbox-primary no-margin">
                                    <input type="checkbox" id="gamesEgg{{ $egg->id }}" name="eggs[]" value="{{ $egg->id }}" @checked(in_array($egg->id, array_map('intval', (array) $offered), true))>
                                    <label for="gamesEgg{{ $egg->id }}">{{ $egg->name }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group no-margin">
                    <label for="gamesProfiles" class="control-label">{{ __('admin/addons.games.max_profiles') }}</label>
                    <input type="number" id="gamesProfiles" name="max_profiles" min="0" max="20" class="form-control" value="{{ old('max_profiles', $addon->maxProfiles()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.games.max_profiles_help') }}</p>
                </div>
            </div>
        </div>
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.games.notice') }}</p>
            </div>
        </div>
    </div>
</div>
