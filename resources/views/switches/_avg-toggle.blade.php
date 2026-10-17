{{-- "1 min / 5 min average" for the graphs on this page (periods up to 48 hours); the choice is remembered in the browser. --}}
<div class="btn-group btn-group-sm mb-2 js-avg" role="group" aria-label="Average" title="Average for 1 hour – 48 hours (Weekly and longer already use 30-minute or longer averages)">
    <button type="button" class="btn btn-outline-secondary" data-avg="1">1 min avg</button>
    <button type="button" class="btn btn-outline-secondary" data-avg="5">5 min avg</button>
</div>
@once
    @push('js')
    <script>
    (function () {
        function mark() {
            var v = window.TrafficGraph ? TrafficGraph.getAvg() : '1';
            document.querySelectorAll('.js-avg [data-avg]').forEach(function (b) {
                b.classList.toggle('btn-info', b.getAttribute('data-avg') === v);
                b.classList.toggle('btn-outline-secondary', b.getAttribute('data-avg') !== v);
            });
        }
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-avg [data-avg]');
            if (!btn) return;
            TrafficGraph.setAvg(btn.getAttribute('data-avg'));
            mark();
        });
        document.addEventListener('DOMContentLoaded', mark);
        if (document.readyState !== 'loading') setTimeout(mark, 0);
    })();
    </script>
    @endpush
@endonce
