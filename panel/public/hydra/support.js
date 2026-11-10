/*
 * Hydra: game support in the admin area (addon "gamesupport").
 *
 * Every open server is a tab. Only the visible tab is connected to the
 * websocket of its node: console, usage and the state come from there, the
 * power buttons and commands go there. Tabs close themselves when their four
 * hours are over.
 */
(function () {
    'use strict';

    var root = document.getElementById('hydraSupport');
    if (!root || !window.HydraSupport) return;

    var config = window.HydraSupport;
    var text = config.text;
    var MAX_LINES = 1000;
    var current = null;

    // ------------------------------------------------------------------ helpers

    function bytes(value) {
        if (!value || value < 0) return '0 B';
        var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        var i = Math.min(units.length - 1, Math.floor(Math.log(value) / Math.log(1024)));
        return (value / Math.pow(1024, i)).toFixed(i < 2 ? 0 : 2) + ' ' + units[i];
    }

    function duration(ms) {
        var s = Math.max(0, Math.floor(ms / 1000));
        var d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
        if (d > 0) return d + 'd ' + h + 'h';
        if (h > 0) return h + 'h ' + m + 'm';
        return m + 'm ' + (s % 60) + 's';
    }

    function escapeHtml(value) {
        return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    var COLORS = {
        30: '#6b7280', 31: '#f87171', 32: '#4ade80', 33: '#facc15', 34: '#60a5fa', 35: '#e879f9', 36: '#22d3ee', 37: '#e5e7eb',
        90: '#9ca3af', 91: '#fca5a5', 92: '#86efac', 93: '#fde047', 94: '#93c5fd', 95: '#f0abfc', 96: '#67e8f9', 97: '#ffffff',
    };

    // Turns the ANSI colors of a console line into spans; other escape codes are dropped.
    function ansi(line) {
        var out = '';
        var open = false;
        var parts = line.replace(/\r/g, '').split(/\u001b\[([0-9;]*)m/);
        for (var i = 0; i < parts.length; i++) {
            if (i % 2 === 0) {
                out += escapeHtml(parts[i].replace(/\u001b\[[0-9;?]*[A-Za-z]/g, ''));
                continue;
            }
            var color = null, bold = false;
            parts[i].split(';').forEach(function (code) {
                code = parseInt(code || '0', 10);
                if (code === 1) bold = true;
                if (COLORS[code]) color = COLORS[code];
            });
            if (open) {
                out += '</span>';
                open = false;
            }
            if (color || bold) {
                out += '<span style="' + (color ? 'color:' + color + ';' : '') + (bold ? 'font-weight:700;' : '') + '">';
                open = true;
            }
        }
        return out + (open ? '</span>' : '');
    }

    function request(url, method, body) {
        var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' };
        if (body !== undefined) headers['Content-Type'] = 'application/json';
        return fetch(url, {
            method: method || 'GET',
            credentials: 'same-origin',
            headers: headers,
            body: body !== undefined ? JSON.stringify(body) : undefined,
        }).then(function (response) {
            if (response.status === 204) return {};
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) {
                    var message = (data.errors && data.errors[0] && data.errors[0].detail) || data.message || response.statusText;
                    throw new Error(message);
                }
                return data;
            });
        });
    }

    // ------------------------------------------------------------------ one tab

    function Pane(el) {
        this.el = el;
        this.id = el.dataset.pane;
        this.socket = null;
        this.closed = false;
        this.lastNetwork = null;
        this.history = [];
        this.historyIndex = -1;
        this.limits = {
            cpu: parseInt(el.dataset.cpuLimit, 10) || 0,
            memory: parseInt(el.dataset.memoryLimit, 10) || 0,
            disk: parseInt(el.dataset.diskLimit, 10) || 0,
        };

        var self = this;
        el.querySelectorAll('[data-power]').forEach(function (button) {
            button.addEventListener('click', function () { self.send('set state', button.dataset.power); });
        });
        var form = el.querySelector('[data-command-form]');
        if (form) {
            var input = form.querySelector('[data-command]');
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var command = input.value.trim();
                if (!command) return;
                self.send('send command', command);
                self.history.unshift(command);
                self.history = self.history.slice(0, 50);
                self.historyIndex = -1;
                input.value = '';
            });
            input.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
                event.preventDefault();
                self.historyIndex = Math.max(-1, Math.min(self.history.length - 1, self.historyIndex + (event.key === 'ArrowUp' ? 1 : -1)));
                input.value = self.historyIndex >= 0 ? self.history[self.historyIndex] : '';
            });
        }
        el.querySelector('[data-reconnect]').addEventListener('click', function () { self.connect(); });
        this.setupManagement();

        el.querySelector('[data-limit="memory"]').textContent = this.limits.memory ? bytes(this.limits.memory) : text.unlimited;
        el.querySelector('[data-limit="disk"]').textContent = this.limits.disk ? bytes(this.limits.disk) : text.unlimited;
    }

    // Hydra: suspend / unsuspend / reinstall / transfer. The page is loaded
    // again afterwards, so the tab shows the new state.
    Pane.prototype.setupManagement = function () {
        var self = this;
        var el = this.el;
        var message = el.querySelector('[data-manage-message]');

        var done = function (data) {
            if (message) {
                message.className = 'hydra-support-manage-message is-success';
                message.textContent = data.message || '';
            }
            setTimeout(function () {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', self.id);
                window.location.href = url.toString();
            }, 900);
        };
        var failed = function (error) {
            el.querySelectorAll('[data-action], [data-transfer-submit]').forEach(function (button) { button.disabled = false; });
            if (message) {
                message.className = 'hydra-support-manage-message is-error';
                message.textContent = error.message;
            }
        };

        el.querySelectorAll('[data-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!window.confirm(button.dataset.confirm)) return;
                button.disabled = true;
                var body = button.dataset.action === 'reinstall' ? {} : { action: button.dataset.action };
                request(button.dataset.url, 'POST', body).then(done).catch(failed);
            });
        });

        var form = el.querySelector('[data-transfer-form]');
        if (!form) return;

        var nodes = (config.transferNodes || []).filter(function (node) {
            return String(node.id) !== form.dataset.node && node.allocations.length > 0;
        });
        var nodeSelect = form.querySelector('[data-transfer-node]');
        var primary = form.querySelector('[data-transfer-allocation]');
        var additional = form.querySelector('[data-transfer-additional]');
        var option = function (value, label) {
            var o = document.createElement('option');
            o.value = value;
            o.textContent = label;
            return o;
        };
        var fillAllocations = function () {
            var node = nodes.filter(function (item) { return String(item.id) === nodeSelect.value; })[0];
            primary.innerHTML = '';
            additional.innerHTML = '';
            (node ? node.allocations : []).forEach(function (allocation) {
                primary.appendChild(option(allocation.id, allocation.text));
                additional.appendChild(option(allocation.id, allocation.text));
            });
        };

        if (nodes.length === 0) {
            form.querySelector('[data-transfer-empty]').hidden = false;
            form.querySelector('[data-transfer-fields]').hidden = true;
            form.querySelector('[data-transfer-submit]').disabled = true;
        } else {
            nodes.forEach(function (node) { nodeSelect.appendChild(option(node.id, node.text)); });
            fillAllocations();
            nodeSelect.addEventListener('change', fillAllocations);
        }

        el.querySelectorAll('[data-transfer-toggle]').forEach(function (button) {
            button.addEventListener('click', function () { form.hidden = !form.hidden; });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var extra = Array.prototype.filter.call(additional.options, function (o) { return o.selected && o.value !== primary.value; })
                .map(function (o) { return parseInt(o.value, 10); });
            form.querySelector('[data-transfer-submit]').disabled = true;
            request(form.dataset.url, 'POST', {
                node_id: parseInt(nodeSelect.value, 10),
                allocation_id: parseInt(primary.value, 10),
                allocation_additional: extra,
            }).then(function (data) {
                form.hidden = true;
                done(data);
            }).catch(failed);
        });
    };

    Pane.prototype.connect = function () {
        var self = this;
        this.disconnect();
        this.closed = false;
        this.authed = false;
        this.lastNetwork = null;
        this.setError(null);
        this.setState('connecting');
        // The node sends the recent log again after connecting.
        this.el.querySelector('[data-console]').innerHTML = '';

        request(this.el.dataset.websocket).then(function (data) {
            if (self.closed) return;
            var socket = new WebSocket(data.socket);
            self.socket = socket;
            socket.onopen = function () { self.send('auth', data.token); };
            socket.onmessage = function (event) { self.receive(JSON.parse(event.data)); };
            socket.onclose = function () {
                if (self.socket === socket) {
                    self.socket = null;
                    if (!self.closed) self.setState('disconnected');
                }
            };
        }).catch(function (error) {
            self.setState('disconnected');
            self.setError(text.connectionError.replace(':error', error.message));
        });
    };

    Pane.prototype.disconnect = function () {
        this.closed = true;
        if (this.socket) {
            var socket = this.socket;
            this.socket = null;
            socket.close();
        }
    };

    Pane.prototype.send = function (event, arg) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ event: event, args: arg === undefined ? [] : [arg] }));
        }
    };

    Pane.prototype.refreshToken = function () {
        var self = this;
        request(this.el.dataset.websocket).then(function (data) { self.send('auth', data.token); }).catch(function (error) {
            self.setError(text.connectionError.replace(':error', error.message));
        });
    };

    Pane.prototype.receive = function (message) {
        var args = message.args || [];
        switch (message.event) {
            case 'auth success':
                // Also sent after a renewed token; the log is only fetched once.
                if (!this.authed) {
                    this.authed = true;
                    this.send('send logs');
                    this.send('send stats');
                }
                break;
            case 'token expiring':
                this.refreshToken();
                break;
            case 'token expired':
                this.connect();
                break;
            case 'jwt error':
                this.setError(text.connectionError.replace(':error', args[0] || ''));
                break;
            case 'console output':
            case 'install output':
            case 'daemon message':
            case 'daemon error':
                this.print(args[0] || '', message.event === 'daemon error');
                break;
            case 'status':
                this.setState(args[0]);
                break;
            case 'stats':
                try {
                    this.stats(JSON.parse(args[0]));
                } catch (e) { /* ignore broken stats */ }
                break;
        }
    };

    Pane.prototype.print = function (output, error) {
        var box = this.el.querySelector('[data-console]');
        var follow = box.scrollTop + box.clientHeight >= box.scrollHeight - 30;
        output.split('\n').forEach(function (line) {
            if (line === '') return;
            var div = document.createElement('div');
            if (error) div.className = 'is-error';
            div.innerHTML = ansi(line);
            box.appendChild(div);
        });
        while (box.childElementCount > MAX_LINES) box.removeChild(box.firstChild);
        if (follow) box.scrollTop = box.scrollHeight;
    };

    Pane.prototype.setState = function (state) {
        this.el.dataset.serverState = state;
        var badge = this.el.querySelector('[data-state]');
        badge.textContent = text.states[state] || state;
        badge.className = 'hydra-support-state is-' + state;
        var dot = root.querySelector('[data-tab="' + this.id + '"] [data-dot]');
        if (dot) dot.className = 'hydra-support-dot is-' + state;

        var running = state === 'running' || state === 'starting';
        var toggle = function (name, disabled, hidden) {
            var button = this.el.querySelector('[data-power="' + name + '"]');
            if (!button) return;
            button.disabled = disabled;
            if (hidden !== undefined) button.hidden = hidden;
        }.bind(this);
        var known = ['running', 'starting', 'stopping', 'offline'].indexOf(state) >= 0;
        toggle('start', !known || state !== 'offline');
        toggle('restart', !known || state === 'offline');
        toggle('stop', !known || !running, state === 'stopping');
        toggle('kill', !known, state !== 'stopping');
    };

    Pane.prototype.stats = function (data) {
        var el = this.el;
        var set = function (name, value) { el.querySelector('[data-stat="' + name + '"]').textContent = value; };
        var bar = function (name, percent) {
            var node = el.querySelector('[data-bar="' + name + '"]');
            if (node) node.style.width = Math.max(0, Math.min(100, percent)) + '%';
        };

        set('cpu', (data.cpu_absolute || 0).toFixed(1) + ' %');
        bar('cpu', this.limits.cpu ? (data.cpu_absolute / this.limits.cpu) * 100 : 0);
        set('memory', bytes(data.memory_bytes));
        bar('memory', this.limits.memory ? (data.memory_bytes / this.limits.memory) * 100 : 0);
        set('disk', bytes(data.disk_bytes));
        bar('disk', this.limits.disk ? (data.disk_bytes / this.limits.disk) * 100 : 0);

        var network = data.network || {};
        var now = Date.now();
        if (this.lastNetwork) {
            var seconds = Math.max(1, (now - this.lastNetwork.at) / 1000);
            var down = Math.max(0, (network.rx_bytes - this.lastNetwork.rx) / seconds);
            var up = Math.max(0, (network.tx_bytes - this.lastNetwork.tx) / seconds);
            set('network', '↓ ' + bytes(down) + '/s  ↑ ' + bytes(up) + '/s');
        }
        this.lastNetwork = { at: now, rx: network.rx_bytes || 0, tx: network.tx_bytes || 0 };
        set('network-total', '↓ ' + bytes(network.rx_bytes) + '  ↑ ' + bytes(network.tx_bytes));
        set('uptime', data.uptime ? duration(data.uptime) : '–');
        if (data.state) this.setState(data.state);
    };

    Pane.prototype.setError = function (message) {
        var box = this.el.querySelector('[data-error]');
        box.hidden = !message;
        box.querySelector('[data-error-text]').textContent = message || '';
    };

    // ------------------------------------------------------------------ tabs

    var search = document.getElementById('hydraSupportSearch');

    // Hydra: while a server is shown the search is hidden; "Close view" goes
    // back to it. The open tabs stay, nothing is connected meanwhile.
    var content = root.querySelector('.tab-content');

    function showSearch(visible) {
        if (search) search.hidden = !visible;
        // Without a shown server the empty area below the tabs goes too.
        if (content) content.hidden = visible;
    }

    function setUrlTab(id) {
        var url = new URL(window.location.href);
        if (id) url.searchParams.set('tab', id);
        else url.searchParams.delete('tab');
        window.history.replaceState(null, '', url.toString());
    }

    function closeView() {
        if (current) current.disconnect();
        current = null;
        root.querySelectorAll('[data-tab]').forEach(function (li) { li.classList.remove('active'); });
        Object.keys(panes).forEach(function (key) { panes[key].el.hidden = true; });
        showSearch(true);
        setUrlTab(null);
        var input = search && search.querySelector('input[name="server"]');
        if (input) input.focus();
    }

    var panes = {};
    root.querySelectorAll('[data-pane]').forEach(function (el) { panes[el.dataset.pane] = new Pane(el); });

    function activate(id) {
        if (!panes[id]) {
            var first = Object.keys(panes)[0];
            if (!first) return;
            id = first;
        }
        if (current && current !== panes[id]) current.disconnect();
        current = panes[id];

        root.querySelectorAll('[data-tab]').forEach(function (li) { li.classList.toggle('active', li.dataset.tab === id); });
        Object.keys(panes).forEach(function (key) { panes[key].el.hidden = key !== id; });
        showSearch(false);
        current.connect();
        setUrlTab(id);
    }

    function close(id, button) {
        var pane = panes[id];
        if (!pane) return;
        pane.disconnect();
        request(button.dataset.close, 'DELETE').catch(function () { /* already gone */ });
        pane.el.remove();
        root.querySelector('[data-tab="' + id + '"]').remove();
        delete panes[id];

        if (Object.keys(panes).length === 0) {
            root.closest('.row').remove();
            current = null;
            showSearch(true);
            setUrlTab(null);
            return;
        }
        // Closing the shown server goes back to the search.
        if (current === pane) {
            current = null;
            closeView();
        }
    }

    root.querySelectorAll('[data-close-view]').forEach(function (button) {
        button.addEventListener('click', closeView);
    });

    root.querySelectorAll('[data-tab-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey) return;
            event.preventDefault();
            activate(link.dataset.tabLink);
        });
    });
    root.querySelectorAll('[data-close]').forEach(function (button) {
        button.addEventListener('click', function () { close(button.closest('[data-tab]').dataset.tab, button); });
    });

    // Countdown of every tab; tabs whose time is over close themselves.
    function tick() {
        root.querySelectorAll('[data-tab]').forEach(function (li) {
            var label = li.querySelector('[data-countdown]');
            var left = new Date(label.dataset.expires).getTime() - Date.now();
            if (left <= 0) {
                close(li.dataset.tab, li.querySelector('[data-close]'));
                return;
            }
            label.textContent = text.closesIn.replace(':time', duration(left).replace(/ \d+s$/, ''));
        });
    }
    tick();
    setInterval(tick, 30000);

    // A hidden page needs no live console; it connects again when shown.
    document.addEventListener('visibilitychange', function () {
        if (!current) return;
        if (document.hidden) current.disconnect();
        else current.connect();
    });

    if (panes[String(root.dataset.active)]) activate(String(root.dataset.active));
    else closeView();
})();
