{{-- Hydra: live preview for partials.admin.hydra.background-editor. --}}
<script>
    (function () {
        document.querySelectorAll('[data-bg-editor]').forEach(function (editor) {
            var preview = editor.querySelector('[data-bg-preview]');
            var image = editor.querySelector('[data-bg-image]');
            var dim = editor.querySelector('[data-bg-dim]');
            var blurInput = editor.querySelector('[data-bg-blur]');
            var dimInput = editor.querySelector('[data-bg-dim-input]');

            function update() {
                // The preview is much smaller than a screen, so the blur is scaled down with it.
                var scale = Math.max(preview.offsetWidth, 1) / Math.max(window.innerWidth, 1);
                image.style.filter = 'blur(' + (blurInput.value * scale).toFixed(2) + 'px)';
                dim.style.opacity = dimInput.value / 100;
                editor.querySelector('[data-bg-blur-value]').textContent = blurInput.value + ' px';
                editor.querySelector('[data-bg-dim-value]').textContent = dimInput.value + ' %';
            }

            editor.querySelector('[data-bg-file]').addEventListener('change', function (event) {
                var file = event.target.files[0];
                if (file) image.style.backgroundImage = 'url(' + URL.createObjectURL(file) + ')';
            });
            blurInput.addEventListener('input', update);
            dimInput.addEventListener('input', update);
            window.addEventListener('resize', update);
            update();
        });
    })();
</script>
