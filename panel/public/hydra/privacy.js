/*
 * Hydra: privacy blur (addon "privacy_blur").
 *
 * Finds IP addresses and email addresses on the page and sets
 * data-hydra-private="ip" / "email" on the element around them; the CSS in
 * layout.css blurs those until the mouse rests on them (or they have the
 * focus). Hostnames cannot be recognised, so the React code marks server
 * addresses itself with the classes "hydra-private-ip" / "hydra-private-email".
 *
 * Built so it can never block the page:
 *  - the text itself is never touched (React owns the text nodes) and only a
 *    data attribute is set, which React leaves alone, so there is no tug of
 *    war over the class list;
 *  - only short texts are looked at (addresses live in short texts, logs and
 *    file contents are long), with cheap checks before any regular expression,
 *    and the expressions run in linear time;
 *  - the console, editors and SVGs are skipped;
 *  - work is done in slices of a few milliseconds per frame.
 *
 * Settings come from window.HydraPrivacy = {ip, email, strength}, printed by
 * partials/hydra/head.blade.php.
 */
(function () {
    var config = window.HydraPrivacy;
    if (!config || (!config.ip && !config.email)) return;

    var root = document.documentElement;
    if (config.ip) root.classList.add('hydra-privacy-ip');
    if (config.email) root.classList.add('hydra-privacy-email');
    root.style.setProperty('--hydra-privacy-blur', (config.strength || 5) + 'px');

    var MAX_LENGTH = 300;
    var BUDGET_MS = 6;
    var ATTR = 'data-hydra-private';

    // Linear: no nested open-ended repetition, short fixed look-arounds.
    var IPV4 = /(?:^|[^\w.])(?:\d{1,3}\.){3}\d{1,3}(?![\w.]?\d)/;
    var IPV6 = /(?:^|[^\w:])[0-9a-f]{0,4}(?::[0-9a-f]{0,4}){2,7}(?![\w:])/i;
    var EMAIL = /[^\s@<>()"',;]{1,64}@[a-z0-9-]{1,63}(?:\.[a-z0-9-]{1,63}){0,6}\.[a-z]{2,24}/i;

    function kind(text) {
        if (!text || text.length < 7 || text.length > MAX_LENGTH) return null;
        if (config.email && text.indexOf('@') !== -1 && EMAIL.test(text)) return 'email';
        if (config.ip) {
            if (text.indexOf('.') !== -1 && /\d\.\d/.test(text) && IPV4.test(text)) return 'ip';
            // IPv6 needs "::" or at least three colons, so times like 12:30:45 do not count.
            if (text.indexOf(':') !== -1 && (text.indexOf('::') !== -1 || text.split(':').length > 3) && IPV6.test(text) && /[0-9a-f]:[0-9a-f]|::/i.test(text)) return 'ip';
        }

        return null;
    }

    // Console, editors and code: blurring single lines there would only get in the way.
    var SKIP = 'script,style,noscript,textarea,svg,.xterm,.CodeMirror,.cm-editor,.ace_editor,[contenteditable="true"],.hydra-privacy-skip';

    function skipped(element) {
        return !element || !element.closest || element.closest(SKIP) !== null;
    }

    function markText(node) {
        var parent = node.parentElement;
        if (!parent) return;
        var found = kind(node.nodeValue);
        if (found) {
            if (parent.getAttribute(ATTR) !== found && !skipped(parent)) parent.setAttribute(ATTR, found);
        } else if (parent.hasAttribute(ATTR) && !kind(parent.textContent)) {
            // The value is gone (e.g. text replaced): no reason to blur any longer.
            parent.removeAttribute(ATTR);
        }
    }

    function markInput(input) {
        var type = (input.type || 'text').toLowerCase();
        if (type !== 'text' && type !== 'email' && type !== 'url' && type !== 'search') return;
        var found = kind(input.value || '');
        var current = input.getAttribute(ATTR);
        if (found && current !== found) {
            if (!skipped(input)) input.setAttribute(ATTR, found);
        } else if (!found && current) {
            input.removeAttribute(ATTR);
        }
    }

    // ------------------------------------------------------------------ queue

    var queue = [];
    var head = 0;
    var scheduled = false;

    function schedule() {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(work);
    }

    function push(node) {
        queue.push(node);
        schedule();
    }

    // Elements are expanded lazily, so even a huge subtree only costs a few
    // milliseconds per frame.
    function work() {
        scheduled = false;
        var start = performance.now();
        while (head < queue.length && performance.now() - start < BUDGET_MS) {
            var node = queue[head++];
            if (!node.isConnected) continue;
            if (node.nodeType === 3) {
                markText(node);
            } else if (node.nodeType === 1) {
                if (node.tagName === 'INPUT') {
                    markInput(node);
                } else if (!node.matches(SKIP)) {
                    for (var child = node.firstChild; child; child = child.nextSibling) {
                        if (child.nodeType === 3 || child.nodeType === 1) queue.push(child);
                    }
                }
            }
        }
        if (head < queue.length) {
            schedule();
        } else {
            queue = [];
            head = 0;
        }
    }

    function start() {
        push(document.body);
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (m.type === 'characterData') {
                    push(m.target);
                } else {
                    for (var j = 0; j < m.addedNodes.length; j++) push(m.addedNodes[j]);
                }
            }
        }).observe(document.body, { childList: true, subtree: true, characterData: true });

        // Values of inputs change without any mutation (React sets the property).
        document.addEventListener('input', function (e) {
            if (e.target && e.target.tagName === 'INPUT') markInput(e.target);
        }, true);
        window.setInterval(function () {
            var inputs = document.getElementsByTagName('input');
            for (var i = 0; i < inputs.length; i++) markInput(inputs[i]);
        }, 2000);
    }

    if (document.body) start();
    else document.addEventListener('DOMContentLoaded', start);
})();
