{{-- Hydra: settings of the Minecraft versions addon. Expects $addon. --}}
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
                <div class="form-group no-margin">
                    <label for="versionsEggs" class="control-label">{{ __('admin/addons.versions.eggs') }}</label>
                    <select id="versionsEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, array_map('intval', $selected), true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{!! __('admin/addons.versions.eggs_help', ['url' => route('admin.design.navigation')]) !!}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.versions.notice') }}</p>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        $('#versionsEggs').select2({ width: '100%' });
    </script>
@endsection
