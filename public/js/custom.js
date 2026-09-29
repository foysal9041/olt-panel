/**
 * Zone dropdown: a searchable Select2 that shows, next to each zone, how
 * many OLTs it has, how many are down and whether it's a POP with its own
 * VLANs — read from the option's data-count / data-down / data-pop.
 * Any <select class="js-zone-select"> is set up automatically.
 */
window.ZoneSelect = {
    template: function (opt) {
        if (!opt.id || !opt.element) return opt.text;
        var d = opt.element.dataset, $ = window.jQuery;
        var row = $('<span class="zs-opt"><span class="zs-name"></span><span class="zs-meta"></span></span>');
        row.find('.zs-name').text(d.name || opt.text);
        var meta = row.find('.zs-meta');
        if (d.count && d.count !== '0') meta.append($('<span class="zs-badge"></span>').text(d.count + ' OLT' + (d.count === '1' ? '' : 's')));
        if (d.down && d.down !== '0') meta.append($('<span class="zs-badge down"></span>').text(d.down + ' down'));
        if (d.pop === '1') meta.append('<span class="zs-badge pop" title="POP with its own VLANs">POP</span>');
        return row;
    },
    init: function (el) {
        var $ = window.jQuery;
        if (!$ || !$.fn.select2) return;
        var $el = $(el);
        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: $el.data('placeholder') || 'Select a zone',
            allowClear: !$el.prop('required') && $el.data('clear') !== false,
            templateResult: window.ZoneSelect.template,
            templateSelection: window.ZoneSelect.template,
        });
        // Open straight into the search box.
        $el.on('select2:open', function () {
            setTimeout(function () {
                var f = document.querySelector('.select2-container--open .select2-search__field');
                if (f) f.focus();
            }, 0);
        });
    },
};

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select.js-zone-select').forEach(function (el) { window.ZoneSelect.init(el); });
});

// A long dropdown list keeps the mouse wheel to itself: at the top or the
// bottom of the list the page underneath doesn't start scrolling.
document.addEventListener('wheel', function (e) {
    var list = e.target.closest && e.target.closest('.select2-results__options');
    if (!list) return;
    var atTop = list.scrollTop <= 0 && e.deltaY < 0;
    var atBottom = Math.ceil(list.scrollTop + list.clientHeight) >= list.scrollHeight && e.deltaY > 0;
    if (atTop || atBottom) e.preventDefault();
}, { passive: false });

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
