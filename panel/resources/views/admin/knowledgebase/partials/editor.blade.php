{{--
    Hydra: Discord style Markdown editor of knowledgebase posts with picture and
    video upload (public/hydra/ticket-editor.js). Expects $name, $value, $required.
--}}
@php($kbLimit = app(\Pterodactyl\Services\Hydra\Addons\KnowledgebaseAddon::class)->uploadLimit())
<div class="hydra-ticket-editor hydra-kb-editor"
     data-markdown-editor
     data-upload-url="{{ route('admin.knowledgebase.upload') }}"
     data-preview-url="{{ route('admin.knowledgebase.preview') }}"
     data-token="{{ csrf_token() }}"
     data-limit="{{ $kbLimit }}"
     data-accept="{{ implode(',', \Pterodactyl\Models\HydraKbMedia::mimes()) }}"
     data-text-uploading="{{ __('admin/tickets.editor.uploading') }}"
     data-text-too-large="{{ __('admin/tickets.editor.too_large', ['size' => $kbLimit]) }}"
     data-text-failed="{{ __('admin/tickets.editor.failed') }}"
     data-text-empty="{{ __('admin/tickets.editor.empty') }}">
    <div class="hydra-ticket-editor-toolbar">
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-default" data-md="heading" title="{{ __('admin/knowledgebase.editor.heading') }}"><x-hydra.icon name="heading" /></button>
            <button type="button" class="btn btn-default" data-md="bold" title="{{ __('admin/tickets.editor.bold') }}"><x-hydra.icon name="bold" /></button>
            <button type="button" class="btn btn-default" data-md="italic" title="{{ __('admin/tickets.editor.italic') }}"><x-hydra.icon name="italic" /></button>
            <button type="button" class="btn btn-default" data-md="underline" title="{{ __('admin/knowledgebase.editor.underline') }}"><x-hydra.icon name="underline" /></button>
            <button type="button" class="btn btn-default" data-md="strike" title="{{ __('admin/knowledgebase.editor.strike') }}"><x-hydra.icon name="strikethrough" /></button>
            <button type="button" class="btn btn-default" data-md="spoiler" title="{{ __('admin/knowledgebase.editor.spoiler') }}"><x-hydra.icon name="eye-off" /></button>
        </div>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-default" data-md="code" title="{{ __('admin/tickets.editor.code') }}"><x-hydra.icon name="code" /></button>
            <button type="button" class="btn btn-default" data-md="quote" title="{{ __('admin/tickets.editor.quote') }}"><x-hydra.icon name="quote" /></button>
            <button type="button" class="btn btn-default" data-md="list" title="{{ __('admin/tickets.editor.list') }}"><x-hydra.icon name="list" /></button>
            <button type="button" class="btn btn-default" data-md="subtext" title="{{ __('admin/knowledgebase.editor.subtext') }}"><x-hydra.icon name="type" /></button>
            <button type="button" class="btn btn-default" data-md="link" title="{{ __('admin/tickets.editor.link') }}"><x-hydra.icon name="link" /></button>
            <button type="button" class="btn btn-default" data-md="image" title="{{ __('admin/knowledgebase.editor.media') }}"><x-hydra.icon name="image" /></button>
        </div>
        <div class="btn-group btn-group-sm pull-right">
            <button type="button" class="btn btn-default active" data-mode="write">{{ __('admin/tickets.editor.write') }}</button>
            <button type="button" class="btn btn-default" data-mode="preview">{{ __('admin/tickets.editor.preview') }}</button>
        </div>
        <input type="file" accept="{{ implode(',', \Pterodactyl\Models\HydraKbMedia::mimes()) }}" multiple hidden data-file>
    </div>
    <textarea name="{{ $name }}" rows="16" class="form-control hydra-ticket-editor-input" @if($required) required @endif maxlength="{{ \Pterodactyl\Models\HydraKbPost::MAX_CONTENT }}" placeholder="{{ __('admin/knowledgebase.editor.placeholder') }}">{{ $value }}</textarea>
    <div class="hydra-markdown hydra-ticket-editor-preview" data-preview hidden></div>
    <p class="text-muted small hydra-ticket-editor-hint">{{ __('admin/knowledgebase.editor.hint', ['size' => $kbLimit]) }}</p>
</div>
