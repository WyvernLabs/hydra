{{-- Hydra: Markdown editor of tickets with image upload (public/hydra/ticket-editor.js). Expects $name and $value. --}}
<div class="hydra-ticket-editor"
     data-ticket-editor
     data-upload-url="{{ route('admin.tickets.upload') }}"
     data-preview-url="{{ route('admin.tickets.preview') }}"
     data-token="{{ csrf_token() }}"
     data-limit="{{ $uploadLimit }}"
     data-text-uploading="{{ __('admin/tickets.editor.uploading') }}"
     data-text-too-large="{{ __('admin/tickets.editor.too_large', ['size' => $uploadLimit]) }}"
     data-text-failed="{{ __('admin/tickets.editor.failed') }}"
     data-text-empty="{{ __('admin/tickets.editor.empty') }}">
    <div class="hydra-ticket-editor-toolbar">
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-default" data-md="bold" title="{{ __('admin/tickets.editor.bold') }}"><x-hydra.icon name="bold" /></button>
            <button type="button" class="btn btn-default" data-md="italic" title="{{ __('admin/tickets.editor.italic') }}"><x-hydra.icon name="italic" /></button>
            <button type="button" class="btn btn-default" data-md="code" title="{{ __('admin/tickets.editor.code') }}"><x-hydra.icon name="code" /></button>
            <button type="button" class="btn btn-default" data-md="quote" title="{{ __('admin/tickets.editor.quote') }}"><x-hydra.icon name="quote" /></button>
            <button type="button" class="btn btn-default" data-md="list" title="{{ __('admin/tickets.editor.list') }}"><x-hydra.icon name="list" /></button>
            <button type="button" class="btn btn-default" data-md="link" title="{{ __('admin/tickets.editor.link') }}"><x-hydra.icon name="link" /></button>
            <button type="button" class="btn btn-default" data-md="image" title="{{ __('admin/tickets.editor.image') }}"><x-hydra.icon name="image" /></button>
        </div>
        <div class="btn-group btn-group-sm pull-right">
            <button type="button" class="btn btn-default active" data-mode="write">{{ __('admin/tickets.editor.write') }}</button>
            <button type="button" class="btn btn-default" data-mode="preview">{{ __('admin/tickets.editor.preview') }}</button>
        </div>
        <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden data-file>
    </div>
    <textarea name="{{ $name }}" rows="8" class="form-control hydra-ticket-editor-input" required maxlength="20000" placeholder="{{ __('admin/tickets.editor.placeholder') }}">{{ $value }}</textarea>
    <div class="hydra-markdown hydra-ticket-editor-preview" data-preview hidden></div>
    <p class="text-muted small hydra-ticket-editor-hint">{{ __('admin/tickets.editor.hint', ['size' => $uploadLimit]) }}</p>
</div>
