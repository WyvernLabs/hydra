{{-- Hydra: scripts of the Lucide icon picker, include once in "footer-scripts". --}}
<script>
    window.HydraIconPickerLang = @json(__('admin/design.icon_picker'));
    window.HydraIconVersion = @json(substr(md5_file(public_path('hydra/lucide.json')), 0, 8));
</script>
<script src="/hydra/icon-picker.js?v={{ substr(md5_file(public_path('hydra/icon-picker.js')), 0, 8) }}"></script>
