{{--
    Hydra: live preview of the designer, right of the settings. $pages is the
    (HTML-escaped) JSON list of [{label, url}] a page offers via
    @section('designer-preview', json_encode([...])). public/hydra/designer.js
    switches pages and devices and sends the unsaved form values into the frame.
--}}
<aside class="hydra-designer-preview" id="hydraDesignerPreview" data-pages="{!! $pages !!}">
    <div class="hydra-designer-preview-bar">
        <div class="hydra-designer-pages" data-designer-pages></div>
        <div class="hydra-designer-devices" role="group" aria-label="{{ __('admin/design.designer.device') }}">
            <button type="button" data-device="desktop" title="{{ __('admin/design.designer.desktop') }}"><x-hydra.icon name="monitor" /><span>{{ __('admin/design.designer.desktop') }}</span></button>
            <button type="button" data-device="tablet" title="{{ __('admin/design.designer.tablet') }}"><x-hydra.icon name="tablet" /><span>{{ __('admin/design.designer.tablet') }}</span></button>
            <button type="button" data-device="mobile" title="{{ __('admin/design.designer.mobile') }}"><x-hydra.icon name="smartphone" /><span>{{ __('admin/design.designer.mobile') }}</span></button>
            <span class="hydra-designer-width" data-designer-width></span>
        </div>
        <div class="hydra-designer-actions">
            <button type="button" class="hydra-icon-btn" data-designer-reload title="{{ __('admin/design.designer.reload') }}"><x-hydra.icon name="refresh-cw" /></button>
            <a href="#" target="_blank" rel="noopener" class="hydra-icon-btn" data-designer-open title="{{ __('admin/design.designer.open') }}"><x-hydra.icon name="external-link" /></a>
        </div>
    </div>
    <div class="hydra-designer-stage" data-designer-stage>
        <div class="hydra-designer-device" data-designer-device>
            <iframe title="{{ __('admin/design.designer.preview') }}" data-designer-frame></iframe>
        </div>
    </div>
    <p class="hydra-designer-hint">{{ __('admin/design.designer.hint') }}</p>
</aside>
