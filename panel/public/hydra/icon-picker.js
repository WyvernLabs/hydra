/*
 * Hydra: picker for Lucide icons in the admin area.
 *
 *   HydraIconPicker.open(current, function (name) { … })   opens the dialog
 *   HydraIconPicker.svg(name, className)                    SVG markup of an icon
 *
 * Fields made with partials/admin/hydra/icon-picker.blade.php work on their own:
 * a button showing the icon plus a hidden input with its name.
 *
 * The icon data (public/hydra/lucide.json) is loaded when it is needed first.
 * Texts come from window.HydraIconPickerLang.
 */
(function () {
    var MAX_RESULTS = 240;
    var data = null;
    var loading = null;
    var lang = window.HydraIconPickerLang || {};

    // The Discord logo is not part of Lucide, the Panel adds it.
    var DISCORD = [['path', {
        d: 'M19.27 5.33A16.6 16.6 0 0 0 15.2 4.06a.06.06 0 0 0-.07.03c-.18.32-.38.73-.52 1.06a15.3 15.3 0 0 0-4.6 0 10.6 10.6 0 0 0-.53-1.06.06.06 0 0 0-.06-.03c-1.43.24-2.8.68-4.07 1.27a.06.06 0 0 0-.03.02C2.73 9.18 2.02 12.94 2.37 16.65a.07.07 0 0 0 .03.05 16.7 16.7 0 0 0 5.03 2.54.06.06 0 0 0 .07-.02c.39-.53.73-1.09 1.03-1.68a.06.06 0 0 0-.03-.09 11 11 0 0 1-1.57-.75.07.07 0 0 1-.01-.11l.31-.24a.06.06 0 0 1 .07-.01c3.29 1.5 6.85 1.5 10.1 0a.06.06 0 0 1 .07.01l.31.24a.07.07 0 0 1-.01.11c-.5.29-1.02.54-1.57.75a.06.06 0 0 0-.03.09c.3.59.65 1.15 1.03 1.68a.06.06 0 0 0 .07.02 16.6 16.6 0 0 0 5.04-2.54.07.07 0 0 0 .03-.05c.42-4.29-.7-8.02-2.98-11.32a.05.05 0 0 0-.03-.02ZM8.68 14.39c-.99 0-1.81-.91-1.81-2.02 0-1.12.8-2.03 1.81-2.03 1.02 0 1.83.92 1.81 2.03 0 1.11-.8 2.02-1.81 2.02Zm6.68 0c-.99 0-1.81-.91-1.81-2.02 0-1.12.8-2.03 1.81-2.03 1.02 0 1.83.92 1.81 2.03 0 1.11-.79 2.02-1.81 2.02Z',
        fill: 'currentColor', stroke: 'none',
    }]];

    function escape(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function load() {
        if (data) return $.Deferred().resolve(data).promise();
        if (!loading) {
            loading = $.getJSON('/hydra/lucide.json?v=' + (window.HydraIconVersion || '')).then(function (json) {
                data = json.icons;
                data.discord = DISCORD;
                return data;
            });
        }
        return loading;
    }

    function svg(name, className) {
        var nodes = (data && (data[name] || data.circle)) || [];
        var inner = nodes.map(function (node) {
            var attributes = Object.keys(node[1]).map(function (key) {
                return ' ' + key + '="' + escape(node[1][key]) + '"';
            }).join('');
            return '<' + node[0] + attributes + '/>';
        }).join('');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="' + escape(className || 'hydra-picker-icon') + '">' + inner + '</svg>';
    }

    var $modal = null;
    var onPick = null;
    var current = null;

    function modal() {
        if ($modal) return $modal;

        $modal = $(
            '<div class="modal fade hydra-icon-picker" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">' +
            '<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="' + escape(lang.close || 'Close') + '"><span aria-hidden="true">&times;</span></button>' +
            '<h4 class="modal-title">' + escape(lang.title || 'Choose icon') + '</h4></div>' +
            '<div class="modal-body">' +
            '<input type="search" class="form-control hydra-icon-picker-search" placeholder="' + escape(lang.search || 'Search …') + '">' +
            '<p class="text-muted small hydra-icon-picker-hint"></p>' +
            '<div class="hydra-icon-picker-grid" role="listbox"></div>' +
            '</div></div></div></div>'
        ).appendTo('body');

        var timer = null;
        $modal.find('.hydra-icon-picker-search').on('input', function () {
            clearTimeout(timer);
            timer = setTimeout(render, 120);
        });
        $modal.on('click', '.hydra-icon-picker-item', function () {
            var name = $(this).data('name');
            $modal.modal('hide');
            if (onPick) onPick(name);
        });
        $modal.on('shown.bs.modal', function () {
            $modal.find('.hydra-icon-picker-search').trigger('focus');
        });

        return $modal;
    }

    function render() {
        var query = $.trim($modal.find('.hydra-icon-picker-search').val()).toLowerCase().replace(/\s+/g, '-');
        var names = Object.keys(data).sort();
        var matches = query ? names.filter(function (name) { return name.indexOf(query) !== -1; }) : names;

        // Exact and prefix matches first.
        if (query) {
            matches.sort(function (a, b) {
                var score = function (name) { return name === query ? 0 : (name.indexOf(query) === 0 ? 1 : 2); };
                return score(a) - score(b) || (a < b ? -1 : 1);
            });
        }

        var shown = matches.slice(0, MAX_RESULTS);
        $modal.find('.hydra-icon-picker-grid').html(shown.map(function (name) {
            return '<button type="button" role="option" class="hydra-icon-picker-item' + (name === current ? ' is-selected' : '') + '" data-name="' + escape(name) + '" title="' + escape(name) + '">' +
                svg(name) + '<span>' + escape(name) + '</span></button>';
        }).join(''));

        var hint = matches.length === 0
            ? (lang.none || 'No icon found.')
            : (matches.length > shown.length ? (lang.more || ':count more – refine the search.').replace(':count', matches.length - shown.length) : '');
        $modal.find('.hydra-icon-picker-hint').text(hint).toggle(hint !== '');
    }

    function open(name, callback) {
        current = name;
        onPick = callback;
        load().then(function () {
            modal().find('.hydra-icon-picker-search').val('');
            render();
            $modal.modal('show');
        });
    }

    window.HydraIconPicker = { open: open, svg: svg, load: load };

    // Fields from partials/admin/hydra/icon-picker.blade.php.
    $(document).on('click', '[data-icon-picker]', function () {
        var $field = $(this).closest('.hydra-icon-field');
        var $input = $field.find('input[type="hidden"]');
        open($input.val(), function (name) {
            $input.val(name).trigger('change');
            $field.find('.hydra-icon-field-preview').html(svg(name));
            $field.find('.hydra-icon-field-name').text(name);
        });
    });
})();
