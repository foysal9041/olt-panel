document.addEventListener('DOMContentLoaded', function () {

    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('.data-table').each(function () {
            jQuery(this).DataTable({
                pageLength: 25,
                order: [],
            });
        });
    }

    document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            e.preventDefault();

            var message = form.dataset.confirmMessage || 'Are you sure?';

            if (window.Swal) {
                Swal.fire({
                    title: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    confirmButtonColor: '#d33',
                }).then(function (result) {
                    if (result.value) {
                        form.dataset.confirmed = 'true';
                        form.submit();
                    }
                });
            } else if (window.confirm(message)) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });
    });

});
