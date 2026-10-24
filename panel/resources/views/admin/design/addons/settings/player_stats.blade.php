{{-- Hydra: settings of the player statistics addon. Expects $addon. --}}
@php
    $nests = \Pterodactyl\Models\Nest::query()->with(['eggs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();
    $selected = array_map('intval', old('eggs', $addon->eggIds()));
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label for="playerStatsEggs" class="control-label">{{ __('admin/addons.player_stats.eggs') }}</label>
                    <select id="playerStatsEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, $selected, true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{{ __('admin/addons.player_stats.eggs_help') }}</p>
                </div>
                <div class="form-group no-margin" style="max-width: 260px;">
                    <label for="playerStatsDays" class="control-label">{{ __('admin/addons.player_stats.keep_days') }}</label>
                    <div class="input-group">
                        <input type="number" id="playerStatsDays" name="keep_days" min="1" max="90" class="form-control" value="{{ old('keep_days', $addon->keepDays()) }}">
                        <span class="input-group-addon">{{ __('admin/addons.player_stats.days') }}</span>
                    </div>
                    <p class="text-muted small no-margin">{{ __('admin/addons.player_stats.keep_days_help') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.player_stats.notice') }}</p>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        $('#playerStatsEggs').select2({ width: '100%' });
    </script>
@endsection
