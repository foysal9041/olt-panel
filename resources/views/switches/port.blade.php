@extends('adminlte::page')

@section('title', ($port->name ?: $port->descr) . ' — ' . $switch->name)

@section('content_header')
<x-noc.header :title="$port->name ?: $port->descr" :back="route('switches.show', $switch)"
    subtitle="{{ $switch->name }} · {{ $switch->ip }}{{ $port->alias ? ' · ' . $port->alias : '' }}{{ $port->speed_label ? ' · ' . $port->speed_label : '' }}">
    <x-slot:badge>
        @if ($port->admin_status === 2)
            <span class="badge badge-secondary">DISABLED</span>
        @elseif ($port->oper_status === \App\Models\SwitchPort::UP)
            <span class="badge badge-success">UP</span>
        @else
            <span class="badge badge-danger">DOWN</span>
        @endif
    </x-slot:badge>
</x-noc.header>
@stop

@section('css')
<style>
    .rx-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem; }
    .rx-stat { padding: .85rem 1rem; border-radius: .75rem; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    .rx-stat-label { font-size: .72rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
    .rx-stat-value { margin-top: .25rem; font-size: 1.35rem; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
    .rx-stat-sub { font-size: .75rem; color: #94a3b8; }
    .rx-change-down { color: #dc2626 !important; }
    .rx-change-up { color: #16a34a !important; }
    .range-btns .btn { margin: 0 .25rem .35rem 0; }
    .rx-journey { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; font-size: 1.05rem; }
    .rx-journey b { font-size: 1.6rem; font-variant-numeric: tabular-nums; color: #0f172a; }
    .rx-journey .arrow { color: #94a3b8; font-size: 1.2rem; }
</style>
@stop

@section('content')

<div class="card card-outline card-primary">
    <div class="card-header d-flex align-items-center flex-wrap">
        <h3 class="card-title mr-auto"><i class="fas fa-chart-line mr-1"></i> Rx / Tx Power History</h3>
        <div class="range-btns" id="range-btns">
            @foreach ($ranges as $key => [$label])
                <button type="button" class="btn btn-sm {{ $loop->first ? 'btn-primary' : 'btn-outline-secondary' }}" data-range="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="card-body">

        <div class="rx-journey mb-3" id="rx-journey">
            <span class="text-muted">Loading…</span>
        </div>

        <div style="position: relative; height: 320px;">
            <canvas id="rx-chart"></canvas>
            <div id="rx-empty" class="text-center text-muted" hidden
                 style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;">
                No readings in this period yet.
            </div>
        </div>

        <div class="rx-stats mt-3" id="rx-stats"></div>

        <div class="small text-muted mt-3">
            <i class="fas fa-info-circle"></i>
            Recorded every minute while the module reports DOM readings, kept for {{ \App\Models\SwitchPortReading::RETENTION_DAYS }} days.
            @if ($firstReading)
                History starts {{ \Illuminate\Support\Carbon::parse($firstReading)->format('d M Y, h:i A') }}.
            @endif
            Shaded band = min–max within each point.
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Now</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><th class="pl-3">Rx power</th><td>{{ $port->rx_power !== null ? $port->rx_power . ' dBm' : '—' }}</td></tr>
                    <tr><th class="pl-3">Tx power</th><td>{{ $port->tx_power !== null ? $port->tx_power . ' dBm' : '—' }}</td></tr>
                    <tr><th class="pl-3">Temperature</th><td>{{ $port->temperature !== null ? $port->temperature . ' °C' : '—' }}</td></tr>
                    <tr><th class="pl-3">Voltage</th><td>{{ $port->voltage !== null ? $port->voltage . ' V' : '—' }}</td></tr>
                    <tr><th class="pl-3">Bias</th><td>{{ $port->bias !== null ? $port->bias . ' mA' : '—' }}</td></tr>
                    <tr>
                        <th class="pl-3">Rx limits</th>
                        <td>
                            @if ($port->rx_low_warn !== null || $port->rx_high_warn !== null)
                                low warn {{ $port->rx_low_warn ?? '—' }} / alarm {{ $port->rx_low_alarm ?? '—' }},
                                high warn {{ $port->rx_high_warn ?? '—' }} / alarm {{ $port->rx_high_alarm ?? '—' }} dBm
                                <div class="small text-muted">reported by the module</div>
                            @elseif ($rxThreshold !== null)
                                alert below {{ $rxThreshold }} dBm
                                <div class="small text-muted">fallback from Settings → Telegram</div>
                            @else
                                <span class="text-muted">none</span>
                            @endif
                        </td>
                    </tr>
                    <tr><th class="pl-3">Last change</th><td>{{ $port->last_change_at?->format('d M Y, h:i A') ?? '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Events on this port</h3></div>
            <div class="card-body p-0">
                @include('switches._events_table', ['events' => $events, 'showSwitch' => false])
            </div>
        </div>
    </div>
</div>

@stop

@section('js')
<script>
(function () {
    var url = @json(route('switches.ports.history', [$switch, $port]));
    var chart = null;
    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function pad(n) { return n < 10 ? '0' + n : n; }
    function when(ts, withDate) {
        var d = new Date(ts * 1000);
        var h = d.getHours(), ap = h >= 12 ? 'PM' : 'AM';
        var t = pad(h % 12 || 12) + ':' + pad(d.getMinutes()) + ' ' + ap;
        return withDate ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ', ' + t : t;
    }
    function dbm(v) { return v === null || v === undefined ? '—' : (+v).toFixed(2) + ' dBm'; }
    function signed(v) { return (v > 0 ? '+' : v < 0 ? '−' : '±') + Math.abs(v).toFixed(2) + ' dB'; }

    function render(d) {
        var multiDay = d.end - d.start > 86400;
        var pts = d.points;
        var st = d.stats;
        var thr = d.thresholds;

        // Journey: "from X → to Y (Δ)"
        var journey = document.getElementById('rx-journey');
        if (st.first === null) {
            journey.innerHTML = '<span class="text-muted">No Rx readings in this period yet.</span>';
        } else {
            var cls = st.change <= -1 ? 'rx-change-down' : (st.change >= 1 ? 'rx-change-up' : '');
            journey.innerHTML =
                '<span class="text-muted">Rx was</span> <b>' + dbm(st.first) + '</b>' +
                '<span class="text-muted small">(' + when(st.first_at, true) + ')</span>' +
                '<span class="arrow"><i class="fas fa-long-arrow-alt-right"></i></span>' +
                '<span class="text-muted">now</span> <b>' + dbm(st.last) + '</b>' +
                '<span class="badge ' + (st.change <= -1 ? 'badge-danger' : st.change >= 1 ? 'badge-success' : 'badge-secondary') + '" style="font-size:.95rem">' +
                    '<i class="fas fa-caret-' + (st.change < 0 ? 'down' : 'up') + '"></i> ' + signed(st.change) + '</span>';
        }

        // Stat tiles
        var drop = st.biggest_drop;
        document.getElementById('rx-stats').innerHTML = [
            ['Lowest', dbm(st.min), ''],
            ['Highest', dbm(st.max), ''],
            ['Average', dbm(st.avg), ''],
            ['Total change', st.change === null ? '—' : signed(st.change), st.change <= -1 ? 'rx-change-down' : st.change >= 1 ? 'rx-change-up' : ''],
            ['Biggest drop', drop ? signed(drop.change) : '—', drop && drop.change <= -1 ? 'rx-change-down' : '',
                drop ? dbm(drop.from) + ' → ' + dbm(drop.to) + '<br>' + when(drop.at, true) : ''],
        ].map(function (s) {
            return '<div class="rx-stat"><div class="rx-stat-label">' + s[0] + '</div>' +
                '<div class="rx-stat-value ' + s[2] + '">' + s[1] + '</div>' +
                (s[3] ? '<div class="rx-stat-sub">' + s[3] + '</div>' : '') + '</div>';
        }).join('');

        document.getElementById('rx-empty').hidden = pts.length > 0;

        var labels = pts.map(function (p) { return when(p[0], multiDay); });
        var line = function (v) { return pts.map(function () { return v; }); };

        var datasets = [
            { label: 'Rx max', data: pts.map(function (p) { return p[3]; }), borderWidth: 0, pointRadius: 0, fill: '+1', backgroundColor: 'rgba(79,70,229,0.12)' },
            { label: 'Rx min', data: pts.map(function (p) { return p[2]; }), borderWidth: 0, pointRadius: 0, fill: false },
            { label: 'Rx', data: pts.map(function (p) { return p[1]; }), borderColor: '#4f46e5', backgroundColor: '#4f46e5', borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: false, lineTension: 0.15, spanGaps: false },
            { label: 'Tx', data: pts.map(function (p) { return p[4]; }), borderColor: '#94a3b8', borderDash: [4, 3], borderWidth: 1.5, pointRadius: 0, fill: false, lineTension: 0.15 },
        ];

        var low = thr.low_warn !== null ? thr.low_warn : thr.fallback;
        if (low !== null && low !== undefined) {
            datasets.push({ label: (thr.low_warn !== null ? 'Low warning ' : 'Alert below ') + low, data: line(low), borderColor: '#f59e0b', borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false });
        }
        if (thr.low_alarm !== null && thr.low_alarm !== undefined && thr.low_alarm !== low) {
            datasets.push({ label: 'Low alarm ' + thr.low_alarm, data: line(thr.low_alarm), borderColor: '#dc2626', borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false });
        }

        if (chart) chart.destroy();

        chart = new Chart(document.getElementById('rx-chart').getContext('2d'), {
            type: 'line',
            data: { labels: labels, datasets: datasets },
            options: {
                maintainAspectRatio: false,
                animation: { duration: 0 },
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, fontColor: '#475569', filter: function (item) { return item.text !== 'Rx max' && item.text !== 'Rx min'; } },
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    filter: function (item) { return item.datasetIndex >= 2 && item.datasetIndex <= 3; },
                    callbacks: {
                        label: function (item, data) {
                            var p = pts[item.index];
                            if (item.datasetIndex === 2) return 'Rx ' + dbm(p[1]) + '  (min ' + dbm(p[2]) + ', max ' + dbm(p[3]) + ')';
                            return 'Tx ' + dbm(p[4]);
                        },
                    },
                },
                scales: {
                    yAxes: [{
                        ticks: { fontColor: '#94a3b8', callback: function (v) { return v + ' dBm'; } },
                        gridLines: { color: '#eef2f7', drawBorder: false },
                    }],
                    xAxes: [{
                        ticks: { fontColor: '#64748b', autoSkip: true, maxTicksLimit: 10, maxRotation: 0 },
                        gridLines: { display: false },
                    }],
                },
            },
        });
    }

    function load(range) {
        fetch(url + '?range=' + range, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(render)
            .catch(function () {
                document.getElementById('rx-journey').innerHTML = '<span class="text-danger">Could not load history.</span>';
            });
    }

    var buttons = document.querySelectorAll('#range-btns [data-range]');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            buttons.forEach(function (b) {
                b.classList.toggle('btn-primary', b === btn);
                b.classList.toggle('btn-outline-secondary', b !== btn);
            });
            load(btn.dataset.range);
        });
    });

    load('24h');
})();
</script>
@stop
