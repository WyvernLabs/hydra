{{-- Hydra: settings of the .env editor addon. Expects $addon. --}}
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
                <div class="form-group">
                    <label for="envPath" class="control-label">{{ __('admin/addons.env.path') }}</label>
                    <input type="text" id="envPath" name="path" maxlength="191" class="form-control" value="{{ old('path', $addon->path()) }}">
                    <p class="text-muted small no-margin">{{ __('admin/addons.env.path_help') }}</p>
                </div>
                <div class="form-group no-margin">
                    <label for="envEggs" class="control-label">{{ __('admin/addons.env.eggs') }}</label>
                    <select id="envEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, array_map('intval', $selected), true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{!! __('admin/addons.env.eggs_help', ['url' => route('admin.design.navigation')]) !!}</p>
                </div>
            </div>
        </div>
    </div>
</div>

@section('footer-scripts')
    @parent
    <script>
        $('#envEggs').select2({ width: '100%' });
    </script>
@endsection
