{{-- Hydra: live updates for admin.nodes.partials.usage. --}}
<script>
    (function () {
        var root = document.getElementById('nodeUsage');
        if (!root) return;

        var lang = @json(__('admin/nodes.usage.js'));
        var circumference = 2 * Math.PI * 42;

        function bytes(value) {
            var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
            var i = 0;
            while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
            return value.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
        }

        function bits(bytesPerSecond) {
            var value = bytesPerSecond * 8;
            var units = ['bit/s', 'Kbit/s', 'Mbit/s', 'Gbit/s'];
            var i = 0;
            while (value >= 1000 && i < units.length - 1) { value /= 1000; i++; }
            return value.toFixed(value >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
        }

        function duration(seconds) {
            var d = Math.floor(seconds / 86400), h = Math.floor(seconds % 86400 / 3600), m = Math.floor(seconds % 3600 / 60);
            return (d ? d + ' ' + lang.days + ' ' : '') + h + ' ' + lang.hours + ' ' + m + ' ' + lang.minutes;
        }

        function level(percent) {
            return percent >= 90 ? 'danger' : percent >= 75 ? 'warning' : 'ok';
        }

        function card(key) {
            return root.querySelector('[data-usage="' + key + '"]');
        }

        function setGauge(key, percent, value, limit) {
            var el = card(key);
            percent = Math.max(0, Math.min(100, percent || 0));
            var ring = el.querySelector('[data-ring]');
            ring.style.strokeDasharray = (circumference * percent / 100) + ' ' + circumference;
            el.dataset.level = level(percent);
            el.querySelector('[data-percent]').textContent = Math.round(percent) + '%';
            el.querySelector('[data-value]').textContent = value;
            el.querySelector('[data-limit]').textContent = limit;
        }

        function setGraph(key, history, field, current, total, bandwidth) {
            var el = card(key);
            var values = history.map(function (sample) { return sample[field] || 0; });
            while (values.length < 60) values.unshift(0);

            // Scale to the busiest second, but at least 1 % of the line so idle traffic stays low.
            var max = Math.max.apply(null, values.concat([bandwidth * 0.01]));
            var points = values.map(function (value, i) {
                return [(i / 59 * 120).toFixed(2), (58 - value / max * 54).toFixed(2)];
            });
            var line = 'M' + points.map(function (p) { return p.join(' '); }).join(' L');
            el.querySelector('[data-line]').setAttribute('d', line);
            el.querySelector('[data-area]').setAttribute('d', line + ' L120 60 L0 60 Z');

            var percent = bandwidth > 0 ? current / bandwidth * 100 : 0;
            el.dataset.level = level(percent);
            el.querySelector('[data-value]').textContent = bits(current);
            el.querySelector('[data-percent]').textContent = (percent < 1 && percent > 0 ? '< 1' : Math.round(percent)) + '% ' + lang.of_line;
            el.querySelector('[data-limit]').textContent = bits(bandwidth);
            el.querySelector('[data-max]').textContent = lang.peak + ' ' + bits(Math.max.apply(null, values));
            el.querySelector('[data-total]').textContent = lang.since_boot + ' ' + bytes(total);
        }

        function render(data) {
            root.querySelector('[data-usage-offline]').hidden = data.online;
            root.classList.toggle('is-offline', !data.online);
            if (!data.online) return;

            var threads = data.cpu_threads || 1;
            setGauge('cpu', data.cpu, (data.cpu * threads).toFixed(1) + '%', (threads * 100) + '% · ' + threads + ' ' + lang.threads);
            setGauge('memory', data.memory_total ? data.memory_used / data.memory_total * 100 : 0, bytes(data.memory_used), bytes(data.memory_total));
            setGauge('disk', data.disk_total ? data.disk_used / data.disk_total * 100 : 0, bytes(data.disk_used), bytes(data.disk_total));
            setGraph('tx', data.history || [], 'tx_bps', data.tx_bps, data.tx_total, data.bandwidth_bps);
            setGraph('rx', data.history || [], 'rx_bps', data.rx_bps, data.rx_total, data.bandwidth_bps);

            root.querySelector('[data-fact="load"]').textContent = (data.load || []).map(function (l) { return l.toFixed(2); }).join(' / ');
            root.querySelector('[data-fact="uptime"]').textContent = duration(data.uptime || 0);
            root.querySelector('[data-fact="servers"]').textContent = data.servers_running + ' / ' + data.servers + ' ' + lang.running;
            root.querySelector('[data-fact="swap"]').textContent = data.swap_total ? bytes(data.swap_used) + ' / ' + bytes(data.swap_total) : lang.none;
        }

        function poll() {
            if (document.visibilityState === 'hidden') {
                setTimeout(poll, 2000);
                return;
            }
            $.ajax({ url: root.dataset.url, dataType: 'json' })
                .done(render)
                .fail(function () { render({ online: false }); })
                .always(function () { setTimeout(poll, 2000); });
        }

        poll();
    })();
</script>
