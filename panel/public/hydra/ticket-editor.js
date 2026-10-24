/*
 * Hydra: Markdown editor of tickets in the admin area. Formatting buttons,
 * images by button, paste or drag and drop (uploaded right away and inserted
 * as Markdown), and a preview rendered by the panel.
 */
(function () {
    'use strict';

    function setup(editor) {
        var textarea = editor.querySelector('textarea');
        var preview = editor.querySelector('[data-preview]');
        var fileInput = editor.querySelector('[data-file]');
        var limit = parseInt(editor.dataset.limit, 10) * 1024 * 1024;

        // Wraps the selection (or a placeholder) or prefixes its lines.
        function wrap(before, after, placeholder) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var selected = textarea.value.substring(start, end) || placeholder;
            textarea.setRangeText(before + selected + after, start, end, 'end');
            textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
            textarea.focus();
        }

        function prefixLines(prefix) {
            var start = textarea.value.lastIndexOf('\n', textarea.selectionStart - 1) + 1;
            var end = textarea.selectionEnd;
            var lines = textarea.value.substring(start, end).split('\n').map(function (line) {
                return prefix + line;
            });
            textarea.setRangeText(lines.join('\n'), start, end, 'end');
            textarea.focus();
        }

        function insert(text) {
            var start = textarea.selectionStart;
            var needsBreak = start > 0 && textarea.value.charAt(start - 1) !== '\n';
            textarea.setRangeText((needsBreak ? '\n' : '') + text + '\n', start, textarea.selectionEnd, 'end');
            textarea.focus();
        }

        function upload(file) {
            if (file.size > limit) {
                alert(editor.dataset.textTooLarge);
                return;
            }

            var marker = '![' + editor.dataset.textUploading + ' ' + file.name + ']()';
            insert(marker);

            var data = new FormData();
            data.append('_token', editor.dataset.token);
            data.append('image', file);

            fetch(editor.dataset.uploadUrl, {
                method: 'POST',
                body: data,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) throw new Error(String(response.status));
                    return response.json();
                })
                .then(function (result) {
                    var name = result.name.replace(/[\[\]]/g, '');
                    textarea.value = textarea.value.replace(marker, '![' + name + '](' + result.url + ')');
                })
                .catch(function () {
                    textarea.value = textarea.value.replace(marker + '\n', '').replace(marker, '');
                    alert(editor.dataset.textFailed);
                });
        }

        function uploadAll(files) {
            Array.prototype.forEach.call(files, function (file) {
                if (/^image\/(png|jpeg|gif|webp)$/.test(file.type)) upload(file);
            });
        }

        editor.querySelectorAll('[data-md]').forEach(function (button) {
            button.addEventListener('click', function () {
                switch (button.dataset.md) {
                    case 'bold':
                        return wrap('**', '**', 'text');
                    case 'italic':
                        return wrap('*', '*', 'text');
                    case 'code':
                        return textarea.value.substring(textarea.selectionStart, textarea.selectionEnd).indexOf('\n') >= 0
                            ? wrap('```\n', '\n```', '')
                            : wrap('`', '`', 'code');
                    case 'quote':
                        return prefixLines('> ');
                    case 'list':
                        return prefixLines('- ');
                    case 'link':
                        return wrap('[', '](https://)', 'text');
                    case 'image':
                        return fileInput.click();
                }
            });
        });

        fileInput.addEventListener('change', function () {
            uploadAll(fileInput.files);
            fileInput.value = '';
        });

        textarea.addEventListener('paste', function (event) {
            var files = event.clipboardData && event.clipboardData.files;
            if (files && files.length > 0) {
                event.preventDefault();
                uploadAll(files);
            }
        });

        textarea.addEventListener('dragover', function (event) {
            event.preventDefault();
        });
        textarea.addEventListener('drop', function (event) {
            if (event.dataTransfer && event.dataTransfer.files.length > 0) {
                event.preventDefault();
                uploadAll(event.dataTransfer.files);
            }
        });

        editor.querySelectorAll('[data-mode]').forEach(function (button) {
            button.addEventListener('click', function () {
                var showPreview = button.dataset.mode === 'preview';
                editor.querySelectorAll('[data-mode]').forEach(function (other) {
                    other.classList.toggle('active', other === button);
                });
                textarea.hidden = showPreview;
                preview.hidden = !showPreview;
                if (!showPreview) return;

                preview.innerHTML = '';
                var data = new FormData();
                data.append('_token', editor.dataset.token);
                data.append('body', textarea.value);
                fetch(editor.dataset.previewUrl, {
                    method: 'POST',
                    body: data,
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (result) {
                        // The panel renders the Markdown without raw HTML.
                        preview.innerHTML = result.html || '<p class="text-muted">' + editor.dataset.textEmpty + '</p>';
                    });
            });
        });

        // Spoilers open on click, like on Discord.
        preview.addEventListener('click', function (event) {
            if (event.target.classList.contains('md-spoiler')) event.target.classList.add('is-revealed');
        });
    }

    document.querySelectorAll('[data-ticket-editor]').forEach(setup);
    document.querySelectorAll('.hydra-ticket-message .hydra-markdown').forEach(function (body) {
        body.addEventListener('click', function (event) {
            if (event.target.classList.contains('md-spoiler')) event.target.classList.add('is-revealed');
        });
    });
})();
