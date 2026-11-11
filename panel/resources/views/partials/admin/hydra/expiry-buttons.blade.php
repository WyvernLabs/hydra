{{-- Hydra: "+ N days" buttons next to the runtime field of a server ([data-expiry-add] with data-days; 0 clears it). --}}
<script>
    (function () {
        function pad(value) { return (value < 10 ? '0' : '') + value; }

        $('[data-expiry-add]').on('click', '[data-days]', function () {
            var input = $(this).closest('form').find('input[name="hydra_expires_at"]');
            var days = parseInt($(this).data('days'), 10);
            if (!days) { input.val(''); return; }

            // From the date in the field if it lies in the future, from now otherwise.
            var base = input.val() ? new Date(input.val()) : new Date();
            if (isNaN(base.getTime()) || base < new Date()) base = new Date();
            base.setDate(base.getDate() + days);
            input.val(base.getFullYear() + '-' + pad(base.getMonth() + 1) + '-' + pad(base.getDate()) + 'T' + pad(base.getHours()) + ':' + pad(base.getMinutes()));
        });
    })();
</script>
