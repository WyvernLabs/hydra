{{-- Hydra: asks before a form with data-kb-confirm="<question>" is sent (deleting). --}}
<script>
    $('form[data-kb-confirm]').on('submit', function (event) {
        var form = this;
        if (form.dataset.confirmed) return;
        event.preventDefault();
        swal({
            type: 'warning',
            title: form.dataset.kbConfirm,
            showCancelButton: true,
            confirmButtonColor: '#d9534f',
            confirmButtonText: @json(__('admin/common.actions.delete')),
            cancelButtonText: @json(__('admin/common.actions.cancel')),
        }, function () {
            form.dataset.confirmed = '1';
            form.submit();
        });
    });
</script>
