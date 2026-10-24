{{-- Hydra: settings of the tickets addon. Expects $addon. --}}
@php
    $allLanguages = \Pterodactyl\Services\Hydra\Addons\TicketsAddon::allLanguages();
    $languages = old('languages', $addon->languages());
    $categories = old('categories', $addon->categories());
@endphp
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.tickets.languages_title') }}</h3>
            </div>
            <div class="box-body">
                <div class="form-group no-margin">
                    <label for="ticketLanguages" class="control-label">{{ __('admin/addons.tickets.languages') }}</label>
                    <select id="ticketLanguages" name="languages[]" class="form-control" multiple>
                        @foreach($allLanguages as $code => $name)
                            <option value="{{ $code }}" @selected(in_array($code, $languages, true))>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    <p class="text-muted small no-margin" style="margin-top: 6px;">{{ __('admin/addons.tickets.languages_help') }}</p>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.tickets.categories') }}</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">{{ __('admin/addons.tickets.categories_help') }}</p>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table" id="ticketCategories">
                    <thead>
                        <tr>
                            @foreach($languages as $code)
                                <th>{{ $allLanguages[$code] ?? $code }}</th>
                            @endforeach
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $index => $category)
                            <tr data-category-row>
                                @foreach($languages as $code)
                                    <td>
                                        @if($loop->first)
                                            <input type="hidden" name="categories[{{ $index }}][id]" value="{{ $category['id'] ?? '' }}">
                                        @endif
                                        <input type="text" name="categories[{{ $index }}][names][{{ $code }}]" class="form-control input-sm" maxlength="64" value="{{ $category['names'][$code] ?? '' }}">
                                    </td>
                                @endforeach
                                <td class="text-right">
                                    <button type="button" class="btn btn-xs btn-danger" data-category-remove title="{{ __('admin/addons.tickets.remove_category') }}"><x-hydra.icon name="trash" /></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <template id="ticketCategoryTemplate">
                    <tr data-category-row>
                        @foreach($languages as $code)
                            <td>
                                @if($loop->first)
                                    <input type="hidden" name="categories[__INDEX__][id]" value="">
                                @endif
                                <input type="text" name="categories[__INDEX__][names][{{ $code }}]" class="form-control input-sm" maxlength="64">
                            </td>
                        @endforeach
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-danger" data-category-remove title="{{ __('admin/addons.tickets.remove_category') }}"><x-hydra.icon name="trash" /></button>
                        </td>
                    </tr>
                </template>
            </div>
            <div class="box-footer">
                <button type="button" class="btn btn-sm btn-default" id="ticketCategoryAdd"><x-hydra.icon name="plus" /> {{ __('admin/addons.tickets.add_category') }}</button>
                <p class="text-muted small no-margin" style="margin-top: 8px;">{{ __('admin/addons.tickets.categories_languages_hint') }}</p>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label for="ticketUploadLimit" class="control-label">{{ __('admin/addons.tickets.upload_limit') }}</label>
                        <div class="input-group">
                            <input type="number" id="ticketUploadLimit" name="upload_limit" min="1" max="50" class="form-control" value="{{ old('upload_limit', $addon->uploadLimit()) }}">
                            <span class="input-group-addon">MB</span>
                        </div>
                        <p class="text-muted small">{{ __('admin/addons.tickets.upload_limit_help') }}</p>
                    </div>
                    <div class="form-group col-sm-6">
                        <label for="ticketMaxOpen" class="control-label">{{ __('admin/addons.tickets.max_open') }}</label>
                        <input type="number" id="ticketMaxOpen" name="max_open" min="0" max="100" class="form-control" value="{{ old('max_open', $addon->maxOpen()) }}">
                        <p class="text-muted small">{{ __('admin/addons.tickets.max_open_help') }}</p>
                    </div>
                </div>
                <div class="checkbox checkbox-primary no-margin">
                    <input type="checkbox" id="ticketNotify" name="notify" value="1" @checked(old('notify', $addon->notifies()))>
                    <label for="ticketNotify">{{ __('admin/addons.tickets.notify') }}</label>
                </div>
                <p class="text-muted small no-margin">{{ __('admin/addons.tickets.notify_help') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small">{{ __('admin/addons.tickets.where') }}</p>
                <p class="text-muted small no-margin">{!! __('admin/addons.tickets.roles_hint', ['url' => route('admin.roles')]) !!}</p>
            </div>
        </div>
    </div>
</div>
@section('footer-scripts')
    @parent
    <script>
    (function () {
        $('#ticketLanguages').select2({ width: '100%' });

        var table = document.querySelector('#ticketCategories tbody');
        var template = document.getElementById('ticketCategoryTemplate');
        var next = table.querySelectorAll('[data-category-row]').length;

        document.getElementById('ticketCategoryAdd').addEventListener('click', function () {
            var html = template.innerHTML.replace(/__INDEX__/g, String(next++));
            table.insertAdjacentHTML('beforeend', html);
            table.lastElementChild.querySelector('input[type=text]').focus();
        });

        table.addEventListener('click', function (event) {
            var button = event.target.closest('[data-category-remove]');
            if (button) button.closest('[data-category-row]').remove();
        });
    })();
</script>
@endsection
