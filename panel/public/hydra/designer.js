/*
 * Hydra: live preview of the designer (admin area > Design).
 *
 * The page lists the previews it offers in data-pages ([{key, label, url}]).
 * The preview frame loads the panel with ?hydra_designer=1; whatever is
 * changed in the form on the left is sent into it right away, without saving:
 *
 *  - accent color and "Boxes & surfaces" as CSS variables (same math as
 *    BrandingService::cssVariables and SurfaceService::cssVariables)
 *  - backgrounds of the login page and the dashboard
 *  - style, picture and texts of the login page
 *
 * The receiving end is in partials/hydra/head.blade.php and lib/designer.ts.
 */
(function () {
    var pane = document.getElementById('hydraDesignerPreview');
    if (!pane) return;

    var frame = pane.querySelector('[data-designer-frame]');
    var stage = pane.querySelector('[data-designer-stage]');
    var device = pane.querySelector('[data-designer-device]');
    var settings = document.querySelector('.hydra-designer-settings') || document;
    var pages = [];
    try { pages = JSON.parse(pane.getAttribute('data-pages')) || []; } catch (e) { pages = []; }
    if (!pages.length) return;

    var WIDTHS = { desktop: 1440, tablet: 834, mobile: 390 };
    var storageKey = 'hydra:designer:' + location.pathname;
    var state = { page: pages[0].key, device: 'desktop' };
    try {
        var saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
        if (saved.device && WIDTHS[saved.device]) state.device = saved.device;
        if (saved.page && pages.some(function (p) { return p.key === saved.page; })) state.page = saved.page;
    } catch (e) { /* storage blocked */ }

    function remember() {
        try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch (e) { /* storage blocked */ }
    }

    function page() {
        return pages.filter(function (p) { return p.key === state.page; })[0] || pages[0];
    }

    // ------------------------------------------------------------------ pages & devices

    var pageBar = pane.querySelector('[data-designer-pages]');
    if (pages.length > 1) {
        pages.forEach(function (p) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = p.label;
            button.setAttribute('data-page', p.key);
            button.addEventListener('click', function () { showPage(p.key); });
            pageBar.appendChild(button);
        });
    }

    function showPage(key) {
        state.page = key;
        remember();
        pageBar.querySelectorAll('[data-page]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-page') === key); });
        frame.src = page().url;
        pane.querySelector('[data-designer-open]').href = page().url;
    }

    function layout() {
        var width = WIDTHS[state.device];
        var available = stage.clientWidth - 2;
        var scale = Math.min(1, available / width);
        var height = stage.clientHeight / scale;
        frame.style.width = width + 'px';
        frame.style.height = height + 'px';
        frame.style.transform = 'scale(' + scale + ')';
        device.style.width = Math.round(width * scale) + 'px';
        device.setAttribute('data-device', state.device);
        pane.querySelector('[data-designer-width]').textContent = width + ' px' + (scale < 1 ? ' · ' + Math.round(scale * 100) + ' %' : '');
        pane.querySelectorAll('[data-device]').forEach(function (b) {
            if (b.tagName === 'BUTTON') b.classList.toggle('active', b.getAttribute('data-device') === state.device);
        });
    }

    pane.querySelectorAll('button[data-device]').forEach(function (button) {
        button.addEventListener('click', function () {
            state.device = button.getAttribute('data-device');
            remember();
            layout();
        });
    });
    pane.querySelector('[data-designer-reload]').addEventListener('click', function () { frame.src = page().url; });
    if (window.ResizeObserver) new ResizeObserver(layout).observe(stage);
    window.addEventListener('resize', layout);

    // ------------------------------------------------------------------ colors

    var valid = /^#[0-9a-fA-F]{6}$/;
    var SCALE = { 50: 0.9, 100: 0.8, 200: 0.6, 300: 0.4, 400: 0.2, 500: 0, 600: -0.15, 700: -0.3, 800: -0.45, 900: -0.6 };

    function channels(hex) {
        return [1, 3, 5].map(function (i) { return parseInt(hex.substr(i, 2), 16); });
    }

    function mix(rgb, ratio) {
        var target = ratio >= 0 ? 255 : 0, amount = Math.abs(ratio);
        return rgb.map(function (c) { return Math.round(c + (target - c) * amount); }).join(' ');
    }

    function field(name) {
        return settings.querySelector('[name="' + name + '"]');
    }

    function value(name) {
        var input = field(name);
        return input ? String(input.value).trim() : null;
    }

    function colorVars(vars) {
        var accent = value('accent');
        if (accent && valid.test(accent)) {
            var rgb = channels(accent);
            Object.keys(SCALE).forEach(function (step) { vars['--hydra-accent-' + step] = mix(rgb, SCALE[step]); });
            var luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;
            vars['--hydra-accent-ink'] = luminance > 0.55 ? '15 26 6' : '255 255 255';
        }

        var box = value('surface[box]'), inner = value('surface[inner]'), border = value('surface[border]');
        if (box && inner && border && valid.test(box) && valid.test(inner) && valid.test(border)) {
            var b = channels(box), i = channels(inner), l = channels(border);
            vars['--hydra-surface-900'] = mix(b, -0.355);
            vars['--hydra-surface-800'] = mix(b, 0);
            vars['--hydra-surface-700'] = mix(i, 0);
            vars['--hydra-surface-650'] = mix(i, 0.032);
            vars['--hydra-surface-600'] = mix(i, 0.093);
            vars['--hydra-line'] = mix(l, 0);
            vars['--hydra-line-600'] = mix(l, 0.057);
            vars['--hydra-line-700'] = mix(l, -0.174);
            vars['--hydra-line-800'] = mix(l, -0.326);
            var alpha = Math.max(10, Math.min(100, parseInt(value('surface[opacity]'), 10) || 100)) / 100;
            var blur = Math.max(0, parseInt(value('surface[blur]'), 10) || 0);
            vars['--hydra-surface-alpha'] = String(alpha);
            vars['--hydra-float-alpha'] = String(Math.max(alpha, 0.88));
            vars['--hydra-line-alpha'] = String(Math.max(0, Math.min(100, parseInt(value('surface[border_opacity]'), 10) || 0)) / 100);
            vars['--hydra-backdrop'] = blur > 0 ? 'blur(' + blur + 'px) saturate(140%)' : 'none';
        }
    }

    // ------------------------------------------------------------------ pictures

    var blobs = {};

    // URL of a picture field: the newly picked file, otherwise the saved picture.
    function picture(name, resetName) {
        var input = field(name);
        if (!input) return undefined;
        var file = input.files && input.files[0];
        if (file) {
            if (!blobs[name] || blobs[name].file !== file) {
                if (blobs[name]) URL.revokeObjectURL(blobs[name].url);
                blobs[name] = { file: file, url: URL.createObjectURL(file) };
            }
            return blobs[name].url;
        }
        var reset = resetName ? field(resetName) : null;
        if (reset && reset.type === 'checkbox' && reset.checked) return null;
        return input.getAttribute('data-current') || null;
    }

    function backgrounds() {
        var result = {};
        ['login', 'dashboard'].forEach(function (slot) {
            if (!field('bg_' + slot + '_blur')) return;
            result[slot] = {
                image: picture('bg_' + slot + '_image'),
                blur: parseInt(value('bg_' + slot + '_blur'), 10) || 0,
                dim: parseInt(value('bg_' + slot + '_dim'), 10) || 0,
            };
        });
        return result;
    }

    function login() {
        var style = settings.querySelector('[name="login_style"]:checked');
        if (!style) return undefined;
        var lang = (document.documentElement.lang || 'en').split('-')[0];
        var text = function (key) { return value('texts[' + key + '][' + lang + ']') || null; };
        var mirror = field('login_mirror');
        return {
            style: style.value,
            mirror: !!(mirror && mirror.checked),
            image: picture('login_image', 'reset_login_image'),
            title: text('title'),
            subtitle: text('subtitle'),
            info: text('info'),
        };
    }

    // ------------------------------------------------------------------ sending

    var queued = false;

    function send() {
        queued = false;
        if (!frame.contentWindow) return;
        var vars = {};
        colorVars(vars);
        var message = { type: 'hydra-designer', vars: vars, state: { backgrounds: backgrounds(), login: login() } };
        try {
            frame.contentWindow.postMessage(message, location.origin);
        } catch (e) { /* frame not ready */ }
    }

    function queue() {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(send);
    }

    // Changing a background shows the page it belongs to.
    settings.addEventListener('change', function (event) {
        var match = /^bg_(login|dashboard)_/.exec(event.target.name || '');
        if (match && state.page !== match[1] && pages.some(function (p) { return p.key === match[1]; })) {
            showPage(match[1]);
        }
        queue();
    });
    settings.addEventListener('input', queue);
    settings.addEventListener('click', function () { window.setTimeout(queue, 0); });
    window.addEventListener('message', function (event) {
        if (event.origin === location.origin && event.data && event.data.type === 'hydra-designer-ready' && event.source === frame.contentWindow) {
            send();
        }
    });
    frame.addEventListener('load', function () { window.setTimeout(send, 50); });

    showPage(state.page);
    layout();
})();
