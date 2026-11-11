{{--
    Hydra: fields of a knowledgebase category. Expects $languages, $defaultLanguage,
    $tree, optionally $category (editing) and $parentId (preset parent).
--}}
@php($editing = isset($category) && $category->exists)
@foreach($languages as $code => $language)
    <div class="form-group">
        <label class="control-label" for="kbName_{{ $code }}_{{ $editing ? $category->id : 'new' }}">
            {{ __('admin/knowledgebase.name') }} ({{ $language }})
            @if($code === $defaultLanguage)<span class="field-required"></span>@else<span class="text-muted small">{{ __('admin/knowledgebase.optional') }}</span>@endif
        </label>
        <input type="text" id="kbName_{{ $code }}_{{ $editing ? $category->id : 'new' }}" name="names[{{ $code }}]" class="form-control" maxlength="80"
               value="{{ old("names.$code", $editing ? ($category->names[$code] ?? '') : '') }}" @if($code === $defaultLanguage) required @endif>
    </div>
@endforeach
<div class="form-group">
    <label class="control-label">{{ __('admin/knowledgebase.parent') }}</label>
    <select name="parent_id" class="form-control" @if($editing && $category->children->isNotEmpty()) disabled @endif>
        <option value="">{{ __('admin/knowledgebase.parent_none') }}</option>
        @foreach($tree as $main)
            @continue($editing && $main->id === $category->id)
            <option value="{{ $main->id }}" @selected((int) old('parent_id', $editing ? $category->parent_id : ($parentId ?? 0)) === $main->id)>{{ $main->name() }}</option>
        @endforeach
    </select>
    <p class="text-muted small no-margin">
        {{ $editing && $category->children->isNotEmpty() ? __('admin/knowledgebase.parent_locked') : __('admin/knowledgebase.parent_help') }}
    </p>
</div>
@if($editing)
    <div class="form-group">
        <label class="control-label">{{ __('admin/knowledgebase.sort') }}</label>
        <input type="number" name="sort" min="0" max="100000" class="form-control" value="{{ old('sort', $category->sort) }}">
        <p class="text-muted small no-margin">{{ __('admin/knowledgebase.sort_help') }}</p>
    </div>
@endif
