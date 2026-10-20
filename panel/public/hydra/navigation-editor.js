/*
 * Hydra: editor of the server navigation (Design > Navigation).
 *
 * The whole navigation lives in `state` and is sent as JSON when the form is
 * saved. Buttons are moved between categories by dragging their handle.
 * Data comes from window.HydraNavigation (see admin/design/navigation.blade.php).
 */
(function () {
    var config = window.HydraNavigation;
    var lang = config.lang;
    var state = JSON.parse(JSON.stringify(config.navigation));
    var $editor = $('#hydraNavEditor');
    var readonly = $editor.data('readonly') === 1 || $editor.data('readonly') === '1';
    var dragging = null;

    function escape(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function icon(name, color) {
        return '<span class="hydra-navitem-icon"' + (color ? ' style="color: ' + escape(color) + '"' : '') + '>' +
            window.HydraIconPicker.svg(name) + '</span>';
    }

    function newId() {
        return 'cat-' + Math.random().toString(36).slice(2, 8);
    }

    function render() {
        $editor.find('select').each(function () {
            if ($(this).data('select2')) $(this).select2('destroy');
        });
        $editor.empty();

        state.categories.forEach(function (category, index) {
            var $box = $('<div class="box hydra-navcat"></div>').attr('data-index', index);
            var names = Object.keys(config.languages).map(function (code) {
                return '<div class="hydra-navcat-name"><label class="control-label small">' + escape(lang.name) + ' (' + escape(config.languages[code]) + ')</label>' +
                    '<input type="text" class="form-control input-sm" maxlength="64" data-name="' + escape(code) + '" value="' + escape((category.name || {})[code] || '') + '"' +
                    ' placeholder="' + escape(index === 0 ? lang.default_name : lang.category) + '"></div>';
            }).join('');

            $box.append(
                '<div class="box-header with-border hydra-navcat-header">' +
                '<div class="hydra-navcat-names">' + names + '</div>' +
                '<div class="hydra-navcat-color"><label class="control-label small">' + escape(lang.color) + '</label>' +
                '<div class="hydra-navcat-color-row"><input type="color" value="' + escape(category.color || config.accent) + '"' + (category.color ? '' : ' disabled') + '>' +
                '<label class="small"><input type="checkbox" data-own-color' + (category.color ? ' checked' : '') + '> ' + escape(lang.use_color) + '</label></div></div>' +
                '<div class="hydra-navcat-actions">' +
                '<button type="button" class="btn btn-xs btn-default" data-move="-1" title="' + escape(lang.move_up) + '"' + (index === 0 ? ' disabled' : '') + '>&uarr;</button>' +
                '<button type="button" class="btn btn-xs btn-default" data-move="1" title="' + escape(lang.move_down) + '"' + (index === state.categories.length - 1 ? ' disabled' : '') + '>&darr;</button>' +
                '<button type="button" class="btn btn-xs btn-danger" data-delete title="' + escape(state.categories.length === 1 ? lang.last_category : lang.delete_category) + '"' + (state.categories.length === 1 ? ' disabled' : '') + '>' + escape(lang.delete_category) + '</button>' +
                '</div></div>'
            );

            var $list = $('<ul class="hydra-navcat-items"></ul>').attr('data-index', index);
            category.items.forEach(function (item, itemIndex) {
                var button = config.buttons[item.key] || { label: item.key };
                var eggs = config.eggs.map(function (egg) {
                    var selected = item.eggs && item.eggs.indexOf(egg.id) !== -1 ? ' selected' : '';
                    return '<option value="' + egg.id + '"' + selected + '>' + escape(egg.nest + ' / ' + egg.name) + '</option>';
                }).join('');

                $list.append(
                    '<li class="hydra-navitem" data-item="' + itemIndex + '">' +
                    '<span class="hydra-navitem-handle" title="' + escape(lang.drag) + '">&#8942;&#8942;</span>' +
                    '<button type="button" class="btn btn-default btn-sm hydra-navitem-pick" title="' + escape(lang.change_icon) + '">' + icon(item.icon, category.color) + '</button>' +
                    '<span class="hydra-navitem-label">' + escape(button.label) + '</span>' +
                    '<div class="hydra-navitem-eggs"><select multiple data-placeholder="' + escape(lang.all_eggs) + '">' + eggs + '</select></div>' +
                    '</li>'
                );
            });
            if (category.items.length === 0) {
                $list.append('<li class="hydra-navcat-empty">' + escape(lang.empty_hint) + '</li>');
            }

            $box.append($('<div class="box-body"></div>').append($list));
            $editor.append($box);
        });

        $editor.find('select').each(function () {
            $(this).select2({ placeholder: $(this).data('placeholder'), width: '100%' });
        });

        if (readonly) {
            $editor.find('input, select, button').prop('disabled', true);
        } else {
            $editor.find('.hydra-navitem').attr('draggable', 'true');
        }
    }

    function category($el) {
        return state.categories[$el.closest('.hydra-navcat').data('index')];
    }

    function item($el) {
        return category($el).items[$el.closest('.hydra-navitem').data('item')];
    }

    // Names, color and eggs change the state without drawing everything again.
    $editor.on('input', '[data-name]', function () {
        var cat = category($(this));
        cat.name = cat.name && !Array.isArray(cat.name) ? cat.name : {};
        cat.name[$(this).data('name')] = $(this).val();
    });

    $editor.on('input change', 'input[type="color"]', function () {
        category($(this)).color = $(this).val();
        $(this).closest('.hydra-navcat').find('.hydra-navitem-icon').css('color', $(this).val());
    });

    $editor.on('change', '[data-own-color]', function () {
        var $color = $(this).closest('.hydra-navcat').find('input[type="color"]');
        var cat = category($(this));
        cat.color = this.checked ? $color.val() : null;
        $color.prop('disabled', !this.checked);
        $(this).closest('.hydra-navcat').find('.hydra-navitem-icon').css('color', cat.color || '');
    });

    $editor.on('change', '.hydra-navitem-eggs select', function () {
        var values = ($(this).val() || []).map(Number);
        item($(this)).eggs = values.length ? values : null;
    });

    $editor.on('click', '.hydra-navitem-pick', function () {
        var target = item($(this));
        window.HydraIconPicker.open(target.icon, function (name) {
            target.icon = name;
            render();
        });
    });

    $editor.on('click', '[data-move]', function () {
        var index = $(this).closest('.hydra-navcat').data('index');
        var to = index + Number($(this).data('move'));
        var moved = state.categories.splice(index, 1)[0];
        state.categories.splice(to, 0, moved);
        render();
    });

    // A deleted category hands its buttons to the first remaining one.
    $editor.on('click', '[data-delete]', function () {
        if (state.categories.length < 2) return;
        var index = $(this).closest('.hydra-navcat').data('index');
        var removed = state.categories.splice(index, 1)[0];
        state.categories[0].items = state.categories[0].items.concat(removed.items);
        render();
    });

    $('#hydraNavAdd').on('click', function () {
        state.categories.push({ id: newId(), name: {}, color: null, items: [] });
        render();
    });

    // Drag & drop of buttons, within and between categories.
    $editor.on('dragstart', '.hydra-navitem', function (event) {
        if (!$(event.target).is('.hydra-navitem')) return;
        dragging = { category: $(this).closest('.hydra-navcat').data('index'), item: $(this).data('item') };
        event.originalEvent.dataTransfer.effectAllowed = 'move';
        event.originalEvent.dataTransfer.setData('text/plain', 'hydra-nav');
        $(this).addClass('is-dragging');
    });

    $editor.on('dragend', '.hydra-navitem', function () {
        dragging = null;
        $editor.find('.is-dragging').removeClass('is-dragging');
        $editor.find('.is-drop-target').removeClass('is-drop-target');
    });

    $editor.on('dragover', '.hydra-navcat-items', function (event) {
        if (!dragging) return;
        event.preventDefault();
        $editor.find('.is-drop-target').removeClass('is-drop-target');
        $(this).addClass('is-drop-target');
    });

    $editor.on('drop', '.hydra-navcat-items', function (event) {
        if (!dragging) return;
        event.preventDefault();

        var target = $(this).data('index');
        var y = event.originalEvent.clientY;
        var position = 0;
        $(this).children('.hydra-navitem').each(function () {
            var rect = this.getBoundingClientRect();
            if (y > rect.top + rect.height / 2) position = $(this).data('item') + 1;
        });

        var moved = state.categories[dragging.category].items.splice(dragging.item, 1)[0];
        if (target === dragging.category && position > dragging.item) position--;
        state.categories[target].items.splice(position, 0, moved);
        dragging = null;
        render();
    });

    $('#hydraNavReset').on('click', function (event) {
        if (!window.confirm(lang.reset_confirm)) event.preventDefault();
    });

    $('#hydraNavForm').on('submit', function () {
        $('#hydraNavInput').val(JSON.stringify(state));
    });

    window.HydraIconPicker.load().then(render);
})();
