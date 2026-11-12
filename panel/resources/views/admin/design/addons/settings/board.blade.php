{{-- Hydra: settings of the board addon. Expects $addon. --}}
<div class="row">
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('admin/addons.settings') }}</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label for="boardColumns" class="control-label">{{ __('admin/addons.board.max_columns') }}</label>
                        <input type="number" id="boardColumns" name="max_columns" min="1" max="100" class="form-control" value="{{ old('max_columns', $addon->maxColumns()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.board.max_columns_help') }}</p>
                    </div>
                    <div class="form-group col-sm-6">
                        <label for="boardCards" class="control-label">{{ __('admin/addons.board.max_cards') }}</label>
                        <input type="number" id="boardCards" name="max_cards" min="1" max="5000" class="form-control" value="{{ old('max_cards', $addon->maxCards()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.board.max_cards_help') }}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-6 no-margin">
                        <label for="boardTags" class="control-label">{{ __('admin/addons.board.max_tags') }}</label>
                        <input type="number" id="boardTags" name="max_tags" min="0" max="200" class="form-control" value="{{ old('max_tags', $addon->maxTags()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.board.max_tags_help') }}</p>
                    </div>
                    <div class="form-group col-sm-6 no-margin">
                        <label for="boardItems" class="control-label">{{ __('admin/addons.board.max_items') }}</label>
                        <input type="number" id="boardItems" name="max_items" min="0" max="500" class="form-control" value="{{ old('max_items', $addon->maxItems()) }}">
                        <p class="text-muted small no-margin">{{ __('admin/addons.board.max_items_help') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <div class="box-body">
                <p class="text-muted small no-margin">{{ __('admin/addons.board.notice') }}</p>
            </div>
        </div>
    </div>
</div>
