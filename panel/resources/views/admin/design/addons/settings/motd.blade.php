{{-- Hydra: settings of the Minecraft MOTD addon. Expects $addon. --}}
@php
    $nests = \Pterodactyl\Models\Nest::query()->with(['eggs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get();
    $selected = array_map('intval', old('eggs', $addon->eggIds()));
    $states = $addon->states();
    $favicon = $addon->favicon();
    $colors = ['0' => '#000000', '1' => '#0000AA', '2' => '#00AA00', '3' => '#00AAAA', '4' => '#AA0000', '5' => '#AA00AA', '6' => '#FFAA00', '7' => '#AAAAAA', '8' => '#555555', '9' => '#5555FF', 'a' => '#55FF55', 'b' => '#55FFFF', 'c' => '#FF5555', 'd' => '#FF55FF', 'e' => '#FFFF55', 'f' => '#FFFFFF'];
@endphp
<div class="row">
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label for="motdEggs" class="control-label">{{ __('admin/addons.motd.eggs') }}</label>
                    <select id="motdEggs" name="eggs[]" class="form-control" multiple>
                        @foreach($nests as $nest)
                            @continue($nest->eggs->isEmpty())
                            <optgroup label="{{ $nest->name }}">
                                @foreach($nest->eggs as $egg)
                                    <option value="{{ $egg->id }}" @selected(in_array($egg->id, $selected, true))>{{ $egg->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{{ __('admin/addons.motd.eggs_help') }}</p>
                </div>
                <div class="form-group no-margin">
                    <label for="motdPicture" class="control-label">{{ __('admin/addons.motd.picture') }}</label>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <img id="motdPicturePreview" src="{{ $favicon ? 'data:image/png;base64,' . $favicon : '' }}" alt="" width="64" height="64" style="image-rendering: pixelated; border-radius: 4px; background: #2b2b2b; {{ $favicon ? '' : 'visibility: hidden;' }}">
                        <div style="flex: 1;">
                            <input type="file" id="motdPicture" name="favicon_upload" accept="image/png,image/jpeg,image/gif,image/webp" class="form-control">
                            @if($favicon)
                                <div class="checkbox checkbox-primary" style="margin-bottom: 0;">
                                    <input type="checkbox" id="motdPictureRemove" name="favicon_remove" value="1">
                                    <label for="motdPictureRemove">{{ __('admin/addons.motd.picture_remove') }}</label>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{{ __('admin/addons.motd.picture_help') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small">{{ __('admin/addons.motd.codes_help') }}</p>
                <div class="hydra-motd-palette">
                    @foreach($colors as $code => $hex)
                        <button type="button" class="hydra-motd-swatch" data-code="&{{ $code }}" title="&amp;{{ $code }}" style="background: {{ $hex }};"></button>
                    @endforeach
                    @foreach(['l' => 'B', 'o' => 'I', 'n' => 'U', 'm' => 'S', 'r' => 'R'] as $code => $letter)
                        <button type="button" class="hydra-motd-swatch is-format" data-code="&{{ $code }}" title="&amp;{{ $code }}">{{ $letter }}</button>
                    @endforeach
                </div>
                <p class="text-muted small no-margin" style="margin-top: 10px;">{{ __('admin/addons.motd.notice') }}</p>
            </div>
        </div>
    </div>
</div>
<div class="row">
    @foreach(\Pterodactyl\Services\Hydra\Addons\MotdAddon::STATES as $state)
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __("admin/addons.motd.states.$state") }}</h3>
                </div>
                <div class="box-body" data-motd-state>
                    <div class="hydra-motd-preview">
                        <img data-motd-picture src="{{ $favicon ? 'data:image/png;base64,' . $favicon : '' }}" alt="" style="{{ $favicon ? '' : 'visibility: hidden;' }}">
                        <div class="hydra-motd-preview-body">
                            <div class="hydra-motd-preview-head">
                                <span class="hydra-motd-preview-name">{{ __('admin/addons.motd.preview_name') }}</span>
                                <span data-motd-out="label"></span>
                            </div>
                            <div data-motd-out="line1"></div>
                            <div data-motd-out="line2"></div>
                        </div>
                    </div>
                    @foreach(['line1', 'line2', 'label'] as $field)
                        <div class="form-group @if($loop->last) no-margin @endif">
                            <label class="control-label" for="motd-{{ $state }}-{{ $field }}">{{ __("admin/addons.motd.$field") }}</label>
                            <input type="text" id="motd-{{ $state }}-{{ $field }}" class="form-control hydra-motd-input" data-motd-in="{{ $field }}"
                                   name="states[{{ $state }}][{{ $field }}]" maxlength="{{ $field === 'label' ? 128 : 256 }}"
                                   value="{{ old("states.$state.$field", $states[$state][$field]) }}" style="font-family: monospace;">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

@section('footer-scripts')
    @parent
    <style>
        .hydra-motd-palette { display: flex; flex-wrap: wrap; gap: 4px; }
        .hydra-motd-swatch { width: 26px; height: 26px; border: 1px solid rgba(255,255,255,.25); border-radius: 4px; padding: 0; cursor: pointer; font-weight: bold; color: #ddd; background: #333; }
        .hydra-motd-preview { display: flex; gap: 10px; padding: 8px; margin-bottom: 15px; background: #1e1e1e; border: 2px solid #555; border-radius: 2px; font-family: 'Minecraft', 'Courier New', monospace; font-size: 14px; line-height: 1.3; }
        .hydra-motd-preview img { width: 64px; height: 64px; image-rendering: pixelated; flex-shrink: 0; }
        .hydra-motd-preview-body { flex: 1; min-width: 0; color: #aaa; }
        .hydra-motd-preview-head { display: flex; justify-content: space-between; gap: 8px; }
        .hydra-motd-preview-name { color: #fff; }
        .hydra-motd-preview [data-motd-out] { white-space: pre; overflow: hidden; text-overflow: ellipsis; }
    </style>
    <script>
        (function () {
            $('#motdEggs').select2({ width: '100%' });

            var colors = @json($colors);
            var server = @json(__('admin/addons.motd.preview_name'));
            var panel = @json(parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url'));

            // Same rules as Wings: & codes, hex colors, formats and reset.
            function render(text, element, fallback) {
                element.innerHTML = '';
                var style = { color: fallback, bold: false, italic: false, underline: false, strike: false, magic: false };
                var buffer = '';
                function flush() {
                    if (!buffer) return;
                    var span = document.createElement('span');
                    span.textContent = buffer;
                    span.style.color = style.color;
                    if (style.bold) span.style.fontWeight = 'bold';
                    if (style.italic) span.style.fontStyle = 'italic';
                    var lines = [];
                    if (style.underline) lines.push('underline');
                    if (style.strike) lines.push('line-through');
                    if (lines.length) span.style.textDecoration = lines.join(' ');
                    if (style.magic) span.style.filter = 'blur(2px)';
                    element.appendChild(span);
                    buffer = '';
                }
                text = text.replace(/\{name\}/g, server).replace(/\{panel\}/g, panel);
                for (var i = 0; i < text.length; i++) {
                    var c = text[i];
                    if ((c === '&' || c === '§') && i + 1 < text.length) {
                        var code = text[i + 1].toLowerCase();
                        var hex = text.substr(i + 1, 7);
                        if (colors[code]) { flush(); style = { color: colors[code] }; i++; continue; }
                        if (/^#[0-9a-f]{6}$/i.test(hex)) { flush(); style = { color: hex }; i += 7; continue; }
                        if (code === 'r') { flush(); style = { color: fallback }; i++; continue; }
                        var formats = { l: 'bold', o: 'italic', n: 'underline', m: 'strike', k: 'magic' };
                        if (formats[code]) { flush(); style[formats[code]] = true; i++; continue; }
                    }
                    buffer += c;
                }
                flush();
            }

            function update(box) {
                box.querySelectorAll('[data-motd-in]').forEach(function (input) {
                    var field = input.getAttribute('data-motd-in');
                    render(input.value, box.querySelector('[data-motd-out="' + field + '"]'), field === 'label' ? '#AAAAAA' : '#AAAAAA');
                });
            }

            var last = null;
            document.querySelectorAll('[data-motd-state]').forEach(function (box) {
                box.addEventListener('input', function () { update(box); });
                box.addEventListener('focusin', function (event) { if (event.target.matches('.hydra-motd-input')) last = event.target; });
                update(box);
            });

            // A click on a color puts its code where the cursor of the last text field was.
            document.querySelectorAll('.hydra-motd-swatch').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!last) return;
                    var start = last.selectionStart != null ? last.selectionStart : last.value.length;
                    var end = last.selectionEnd != null ? last.selectionEnd : start;
                    var code = button.getAttribute('data-code');
                    last.value = last.value.slice(0, start) + code + last.value.slice(end);
                    last.focus();
                    last.setSelectionRange(start + code.length, start + code.length);
                    last.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });

            // The chosen picture is shown before saving.
            var picture = document.getElementById('motdPicture');
            picture && picture.addEventListener('change', function () {
                if (!picture.files || !picture.files[0]) return;
                var url = URL.createObjectURL(picture.files[0]);
                document.querySelectorAll('#motdPicturePreview, [data-motd-picture]').forEach(function (img) {
                    img.src = url;
                    img.style.visibility = 'visible';
                    img.style.objectFit = 'cover';
                });
            });
        })();
    </script>
@endsection
