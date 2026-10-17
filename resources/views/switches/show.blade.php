@extends('adminlte::page')

@section('title', $switch->name . ' — ' . $switch->typeLabel())

@section('content_header')
<x-noc.header :title="$switch->name" :back="route('switches.index')"
    subtitle="{{ $switch->ip }} · {{ $switch->vendor_label }}{{ $switch->zone ? ' · ' . $switch->zone : '' }}{{ $switch->notify ? '' : ' · alerts muted' }}{{ $switch->is_active ? '' : ' · paused' }}">
    <x-slot:badge><span data-live="sw-status">@include('switches._status', ['status' => $switch->status])</span></x-slot:badge>
    <form action="{{ route('switches.poll', $switch) }}" method="POST" class="d-inline" id="poll-form">
        @csrf
        <button class="btn btn-primary btn-sm" id="poll-btn"><i class="fas fa-sync-alt"></i> Poll Now</button>
    </form>
    <a href="{{ route('switches.traffic', $switch) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-chart-area"></i> Traffic graphs</a>
    <a href="{{ route('switches.edit', $switch) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
</x-noc.header>
@stop

@section('css')
<style>
    .sw-meta { font-size: .9rem; }
    .sw-meta dt { color: #64748b; font-weight: 500; }
    .port-table td, .port-table th { vertical-align: middle; white-space: nowrap; }
    .port-table .alias { white-space: normal; min-width: 10rem; color: #475569; }
    .rx-good { color: #15803d; font-weight: 600; }
    .rx-warn { color: #b45309; font-weight: 600; }
    .rx-bad  { color: #b91c1c; font-weight: 700; }
    .port-filter .btn { margin: 0 .25rem .35rem 0; }
    .port-muted { opacity: .45; }
    .rx-trend { margin-left: .25rem; font-size: .75rem; font-weight: 600; color: #64748b; white-space: nowrap; }
    .rx-trend-bad { color: #dc2626; }
    .rx-trend-up { color: #16a34a; }
</style>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($switch->last_error && ! session('error'))
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Last poll error: {{ $switch->last_error }}</div>
@endif

@php
    $up = $ports->where('oper_status', \App\Models\SwitchPort::UP)->count();
    $sfp = $ports->filter->has_transceiver->count();
    // Warning level per port: 10G/1G rule from Settings, else the module's
    // own low warning, else the fallback (SwitchPort::rxWarning()).
    // Red once it's past the module's low alarm, or 3 dB past the warning.
    $rxClass = function ($port) use ($settings) {
        $rx = $port->rx_power;
        if ($rx === null) return '';

        $warn = $port->rxWarning($settings)['value'];
        $alarm = $port->rx_low_alarm ?? ($warn !== null ? $warn - 3 : null);

        if (($alarm !== null && $rx < $alarm) || ($port->rx_high_alarm !== null && $rx > $port->rx_high_alarm)) return 'rx-bad';
        if (($warn !== null && $rx < $warn) || ($port->rx_high_warn !== null && $rx > $port->rx_high_warn)) return 'rx-warn';
        return $warn !== null || $port->rx_high_warn !== null ? 'rx-good' : '';
    };
    $rxTitle = function ($port) use ($settings) {
        $w = $port->rxWarning($settings);
        $t = $w['value'] !== null ? "Warning below {$w['value']} dBm ({$w['label']})" : 'No Rx warning level for this port';
        if ($port->rx_low_alarm !== null || $port->rx_high_warn !== null) {
            $t .= ' · module limits: low alarm ' . ($port->rx_low_alarm ?? '—') . ', high warn ' . ($port->rx_high_warn ?? '—') . ', high alarm ' . ($port->rx_high_alarm ?? '—');
        }
        return $t;
    };
@endphp

<div class="row" data-live="sw-summary">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body py-3">
                <dl class="row sw-meta mb-0">
                    <dt class="col-sm-3">System name</dt>
                    <dd class="col-sm-9">{{ $switch->sys_name ?: '—' }}</dd>
                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9 text-truncate" title="{{ $switch->sys_descr }}">{{ $switch->sys_descr ?: '—' }}</dd>
                    <dt class="col-sm-3">Uptime</dt>
                    <dd class="col-sm-9">{{ $switch->uptime_human ?? '—' }}</dd>
                    <dt class="col-sm-3">Last poll</dt>
                    <dd class="col-sm-9 mb-0">{{ $switch->last_polled_at?->format('d M Y, h:i:s A') ?? 'never' }}
                        @if ($switch->last_polled_at) <span class="text-muted">({{ $switch->last_polled_at->diffForHumans() }})</span> @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="row">
            <div class="col-6">
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-success"><i class="fas fa-plug"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Ports Up</span>
                        <span class="info-box-number">{{ $up }} / {{ $ports->count() }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-info"><i class="fas fa-lightbulb"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">SFP (DOM)</span>
                        <span class="info-box-number">{{ $sfp }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php $graphTypes = $switch->graphTypes(); $sensors = $switch->sensors()->get(); @endphp
<div class="card card-outline card-warning" id="device-graphs">
    <div class="card-header d-flex align-items-center flex-wrap" style="gap:.5rem">
        <h3 class="card-title mr-auto"><i class="fas fa-chart-area mr-1 text-warning"></i> Graphs</h3>
        <span class="small" data-live="dev-now">
            @if ($switch->hasCurrentCpu())
                CPU <strong class="{{ $switch->cpu_percent >= 85 ? 'text-danger' : ($switch->cpu_percent >= 60 ? 'text-warning' : 'text-success') }}">{{ $switch->cpu_percent }}%</strong>
            @endif
            @if ($switch->mem_percent !== null)
                <span class="ml-2">Memory <strong class="{{ $switch->mem_percent >= 90 ? 'text-danger' : ($switch->mem_percent >= 75 ? 'text-warning' : 'text-success') }}">{{ $switch->mem_percent }}%</strong></span>
            @endif
            @if ($switch->snmp_ms !== null)<span class="ml-2 text-muted">SNMP {{ $switch->snmp_ms }} ms</span>@endif
        </span>
    </div>
    <div class="card-body">
        @include('switches._periods', ['target' => '.dev-tg'])
        <div class="row">
            @foreach ($graphTypes as $gt)
                <div class="col-md-6 col-xl-4 mb-3">
                    <div class="dg-card">
                        <a href="{{ route('graph.show', ['type' => $gt, 'id' => $switch->id]) }}" class="dg-title text-reset">{{ \App\Support\Ui::t(\App\Services\GraphEngine::TYPES[$gt][0]) }} <i class="fas fa-angle-right text-muted"></i></a>
                        <div class="tg dev-tg" data-compact data-points="200" data-zoom data-url="{{ route('graph.data', ['type' => $gt, 'id' => $switch->id]) }}" data-graph="day"
                             data-page-url="{{ route('graph.show', ['type' => $gt, 'id' => $switch->id]) }}">
                            <div class="tg-plot" style="height:130px"><canvas></canvas></div>
                            <div class="tg-legend"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($sensors->isNotEmpty())
            <h6 class="font-weight-bold mt-1"><i class="fas fa-heartbeat text-danger mr-1"></i> Health</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0" data-live-table="sensors">
                    <thead class="thead-light"><tr><th>Sensor</th><th>Type</th><th class="text-right">Now</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($sensors as $sn)
                            <tr data-key="{{ $sn->id }}">
                                <td>{{ $sn->name }}</td>
                                <td class="text-muted">{{ \App\Models\DeviceSensor::CLASSES[$sn->class][0] ?? $sn->class }}</td>
                                <td class="text-right font-weight-bold">{{ $sn->formatted() }}</td>
                                <td class="text-right"><a href="{{ route('graph.show', ['type' => 'device_' . $sn->class, 'id' => $switch->id]) }}" class="small"><i class="fas fa-chart-line"></i> Graph</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">Ports &amp; Transceivers</h3>
    </div>

    <div class="card-body pb-0">
        <div class="port-filter" id="port-filter">
            <button type="button" class="btn btn-sm btn-primary" data-filter="all">All (<span data-live="pf-all">{{ $ports->count() }}</span>)</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="up">Up (<span data-live="pf-up">{{ $up }}</span>)</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="down">Down (<span data-live="pf-down">{{ $ports->count() - $up }}</span>)</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="sfp">SFP only (<span data-live="pf-sfp">{{ $sfp }}</span>)</button>
        </div>
    </div>

    <div class="card-body p-0 table-responsive">

        <table class="table table-sm table-hover port-table mb-0" data-live-table="ports">
            <thead class="thead-light">
                <tr>
                    <th class="pl-3">Port</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Speed</th>
                    <th title="Traffic now (last minute): in / out">Traffic <small class="text-muted text-lowercase">in / out</small></th>
                    <th title="Current Rx, with the change over the last 24 hours">Rx (dBm) <small class="text-muted text-lowercase">24h Δ</small></th>
                    <th>Tx (dBm)</th>
                    <th>Temp (°C)</th>
                    <th>Voltage (V)</th>
                    <th>Bias (mA)</th>
                    <th>Last Change</th>
                    <th class="text-center">Alert</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ports as $port)
                    @php
                        $isUp = $port->oper_status === \App\Models\SwitchPort::UP;
                        $adminDown = $port->admin_status === 2;
                    @endphp
                    <tr data-key="{{ $port->id }}" data-state="{{ $isUp ? 'up' : 'down' }}" data-sfp="{{ $port->has_transceiver ? 1 : 0 }}"
                        class="{{ $adminDown ? 'port-muted' : '' }}">
                        <td class="pl-3">
                            <a href="{{ route('switches.ports.show', [$switch, $port]) }}" class="text-reset" title="Rx/Tx history">
                                <strong>{{ $port->name ?: $port->descr }}</strong>
                            </a>
                            @if ($port->name && $port->descr && $port->descr !== $port->name)
                                <div class="small text-muted">{{ $port->descr }}</div>
                            @endif
                        </td>
                        <td class="alias">{{ $port->alias ?: '' }}</td>
                        <td>
                            @if ($adminDown)
                                <span class="badge badge-secondary">DISABLED</span>
                            @elseif ($isUp)
                                <span class="badge badge-success">UP</span>
                            @else
                                <span class="badge badge-danger">DOWN</span>
                            @endif
                        </td>
                        <td>{{ $port->speed_label ?? '—' }}</td>
                        <td class="text-nowrap">
                            @if ($port->hasCurrentTraffic())
                                <a href="{{ route('switches.ports.show', [$switch, $port]) }}#traffic" class="text-reset small" title="Traffic graphs">
                                    <span class="text-success"><i class="fas fa-arrow-down"></i> {{ \App\Models\SwitchPort::formatBps($port->in_bps) }}</span><br>
                                    <span class="text-primary"><i class="fas fa-arrow-up"></i> {{ \App\Models\SwitchPort::formatBps($port->out_bps) }}</span>
                                </a>
                                @if (($u = $port->utilisation()) !== null)
                                    <div class="progress mt-1" style="height:3px; width:4.5rem" title="{{ number_format($u, 1) }}% of {{ $port->speed_label }}">
                                        <div class="progress-bar {{ $u >= 90 ? 'bg-danger' : ($u >= 70 ? 'bg-warning' : 'bg-success') }}" style="width: {{ max(2, $u) }}%"></div>
                                    </div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="{{ $rxClass($port) }}" title="{{ $rxTitle($port) }}">
                            @if ($port->rx_power !== null)
                                <a href="{{ route('switches.ports.show', [$switch, $port]) }}" class="text-reset">{{ $port->rx_power }}</a>
                                @if ($port->rx_alarm) <i class="fas fa-exclamation-triangle" title="Below threshold"></i> @endif
                                @if ($t = $rxTrend[$port->id] ?? null)
                                    @if (abs($t['change']) >= 0.1)
                                        <span class="rx-trend {{ $t['change'] <= -1 ? 'rx-trend-bad' : ($t['change'] >= 1 ? 'rx-trend-up' : '') }}"
                                              title="{{ $t['from'] }} dBm {{ $t['since']->diffForHumans() }} → {{ $port->rx_power }} dBm now">
                                            <i class="fas fa-caret-{{ $t['change'] < 0 ? 'down' : 'up' }}"></i>{{ number_format(abs($t['change']), 1) }}
                                        </span>
                                    @endif
                                @endif
                                @php $w = $port->rxWarning($settings); @endphp
                                @if ($w['value'] !== null)
                                    <div class="small text-muted font-weight-normal">warn &lt; {{ $w['value'] }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $port->tx_power ?? '—' }}</td>
                        <td>{{ $port->temperature ?? '—' }}</td>
                        <td>{{ $port->voltage ?? '—' }}</td>
                        <td>{{ $port->bias ?? '—' }}</td>
                        <td>
                            @if ($port->last_change_at)
                                <span title="{{ $port->last_change_at->format('d M Y, h:i:s A') }}">{{ $port->last_change_at->diffForHumans() }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('switches.ports.notify', [$switch, $port]) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-link btn-sm p-0"
                                        title="{{ $port->notify ? 'Alerts on — click to mute' : 'Alerts muted — click to enable' }}">
                                    <i class="fas {{ $port->notify ? 'fa-bell text-primary' : 'fa-bell-slash text-muted' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">
                            No ports yet. Check the SNMP community and click <strong>Poll Now</strong>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>

    <div class="card-footer small text-muted">
        Rx warning: <strong>10G links below {{ $settings->rx_warn_10g ?? '—' }} dBm</strong>,
        <strong>1G links below {{ $settings->rx_warn_1g ?? '—' }} dBm</strong>; other speeds use the module's own limit
        @if ($settings->rx_low_threshold !== null) or {{ $settings->rx_low_threshold }} dBm @endif.
        Colours: <span class="rx-good">normal</span> · <span class="rx-warn">warning</span> · <span class="rx-bad">3 dB past warning / module alarm</span>.
        @can('access-settings-telegram') <a href="{{ route('settings.telegram') }}">Change</a> @endcan
    </div>

</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Events</h3>
        <div class="card-tools">
            <a href="{{ route('switch-events.index', ['switch' => $switch->id]) }}" class="btn btn-tool">View all</a>
        </div>
    </div>
    <div class="card-body p-0" data-live="sw-events">
        @include('switches._events_table', ['events' => $events, 'showSwitch' => false])
    </div>
</div>

@stop

@section('js')
@include('switches._traffic-assets')
<style>
    .dg-card { height: 100%; padding: .5rem .6rem; border: 1px solid #eef2f7; border-radius: .5rem; }
    .dg-title { display: inline-block; font-weight: 700; font-size: .9rem; margin-bottom: .2rem; }
    .dg-card .tg-legend { font-size: .72rem; }
</style>
<script>
(function () {
    var graphs = Array.prototype.slice.call(document.querySelectorAll('.dev-tg'));
    TrafficGraph.lazy(graphs);
    setInterval(function () { if (!document.hidden) graphs.forEach(function (el) { if (el._data) TrafficGraph.load(el); }); }, 60000);
})();
</script>
<script>
LiveRefresh.start(30000);

(function () {
    var buttons = document.querySelectorAll('#port-filter [data-filter]');
    var rows = function () { return document.querySelectorAll('.port-table tbody tr[data-state]'); };
    var current = 'all';

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var f = btn.dataset.filter;
            current = f;

            buttons.forEach(function (b) {
                b.classList.toggle('btn-primary', b === btn);
                b.classList.toggle('btn-outline-secondary', b !== btn);
            });

            rows().forEach(function (row) {
                var show = f === 'all'
                    || (f === 'sfp' && row.dataset.sfp === '1')
                    || row.dataset.state === f;
                row.hidden = !show;
            });

            try { sessionStorage.setItem('port-filter', f); } catch (e) {}
        });
    });

    try {
        var saved = sessionStorage.getItem('port-filter');
        var btn = saved && document.querySelector('#port-filter [data-filter="' + saved + '"]');
        if (btn) btn.click();
    } catch (e) {}

    document.getElementById('poll-form').addEventListener('submit', function () {
        var b = document.getElementById('poll-btn');
        b.disabled = true;
        b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Polling…';
    });

    // Ports update in place every 30s; keep the chosen filter applied.
    document.addEventListener('live:updated', function () {
        var btn = document.querySelector('#port-filter [data-filter="' + current + '"]');
        if (btn) btn.click();
    });
})();
</script>
@stop
