/*
 * Hydra: live countdown of server runtimes in the admin area.
 *
 * Every element with data-hydra-expires="<ISO date>" shows the remaining time
 * ("3d 04:12:09") and gets data-expiry-state = ok | soon (< 24 h) | urgent
 * (< 1 h) | expired. Labels come from window.HydraExpiryLabels
 * ({ day: 'd', expired: 'Expired' }).
 */
(function () {
    var labels = window.HydraExpiryLabels || { day: 'd', expired: 'Expired' };

    function pad(value) {
        return (value < 10 ? '0' : '') + value;
    }

    function format(seconds) {
        var days = Math.floor(seconds / 86400);
        var rest = seconds % 86400;
        var time = pad(Math.floor(rest / 3600)) + ':' + pad(Math.floor((rest % 3600) / 60)) + ':' + pad(rest % 60);

        return days > 0 ? days + labels.day + ' ' + time : time;
    }

    function update() {
        var now = Date.now();
        document.querySelectorAll('[data-hydra-expires]').forEach(function (node) {
            var until = Date.parse(node.getAttribute('data-hydra-expires'));
            if (isNaN(until)) return;
            var seconds = Math.max(0, Math.floor((until - now) / 1000));
            var state = seconds === 0 ? 'expired' : seconds < 3600 ? 'urgent' : seconds < 86400 ? 'soon' : 'ok';
            var target = node.querySelector('[data-hydra-expires-text]') || node;
            target.textContent = seconds === 0 ? labels.expired : format(seconds);
            node.setAttribute('data-expiry-state', state);
        });
    }

    update();
    setInterval(update, 1000);
})();
