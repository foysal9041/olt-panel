{{-- Period buttons (1 hour … Yearly) for the graph whose element id is $target (or every graph matching a ".class" target); $default picks the first one shown. --}}
@php $default ??= 'day'; @endphp
<div class="d-flex flex-wrap align-items-start" style="gap:0 .75rem">
    <div class="btn-group btn-group-sm flex-wrap mb-2 js-periods" role="group" aria-label="Period" data-target="{{ $target }}">
        @foreach (\App\Models\SwitchPortTraffic::GRAPHS as $key => [$title])
            <button type="button" class="btn {{ $key === $default ? 'btn-success' : 'btn-outline-secondary' }}" data-graph="{{ $key }}">{{ \App\Support\Ui::t($title) }}</button>
        @endforeach
    </div>
    @include('switches._avg-toggle')
</div>
@once
    @push('js')
    <script>
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-periods [data-graph]');
        if (!btn) return;
        var group = btn.closest('.js-periods');
        group.querySelectorAll('[data-graph]').forEach(function (b) {
            b.classList.toggle('btn-success', b === btn);
            b.classList.toggle('btn-outline-secondary', b !== btn);
        });
        var target = group.getAttribute('data-target');
        var els = target.charAt(0) === '.' ? document.querySelectorAll(target) : [document.getElementById(target)];
        Array.prototype.forEach.call(els, function (el) { if (el) TrafficGraph.load(el, btn.getAttribute('data-graph')); });
    });
    </script>
    @endpush
@endonce
