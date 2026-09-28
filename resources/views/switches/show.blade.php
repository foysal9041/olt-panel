@extends('adminlte::page')

@section('title', $switch->name.' — Switch')

@section('content_header')
<x-noc.header :title="$switch->name" :back="route('switches.index')"
    subtitle="{{ $switch->ip }} · {{ $switch->vendor_label }}{{ $switch->zone ? ' · ' . $switch->zone : '' }}{{ $switch->notify ? '' : ' · alerts muted' }}{{ $switch->is_active ? '' : ' · paused' }}">
    <x-slot:badge>@include('switches._status', ['status' => $switch->status])</x-slot:badge>
    <form action="{{ route('switches.poll', $switch) }}" method="POST" class="d-inline" id="poll-form">
        @csrf
        <button class="btn btn-primary btn-sm" id="poll-btn"><i class="fas fa-sync-alt"></i> Poll Now</button>
    </form>
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
    $redAt = $rxThreshold ?? -25;
    // Colour by the module's own limits when the switch reports them,
    // otherwise by the global threshold.
    $rxClass = function ($port) use ($redAt) {
        $rx = $port->rx_power;
        if ($rx === null) return '';

        if ($port->rx_low_warn !== null || $port->rx_high_warn !== null) {
            if (($port->rx_low_alarm !== null && $rx < $port->rx_low_alarm) || ($port->rx_high_alarm !== null && $rx > $port->rx_high_alarm)) return 'rx-bad';
            if (($port->rx_low_warn !== null && $rx < $port->rx_low_warn) || ($port->rx_high_warn !== null && $rx > $port->rx_high_warn)) return 'rx-warn';
            return 'rx-good';
        }

        if ($rx < $redAt) return 'rx-bad';
        if ($rx < $redAt + 3) return 'rx-warn';
        return 'rx-good';
    };
    $rxTitle = function ($port) {
        if ($port->rx_low_warn === null && $port->rx_high_warn === null) return 'No limits reported by the module';
        return 'Module limits (dBm) — low alarm: ' . ($port->rx_low_alarm ?? '—') . ', low warn: ' . ($port->rx_low_warn ?? '—')
            . ', high warn: ' . ($port->rx_high_warn ?? '—') . ', high alarm: ' . ($port->rx_high_alarm ?? '—');
    };
@endphp

<div class="row">
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

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">Ports &amp; Transceivers</h3>
    </div>

    <div class="card-body pb-0">
        <div class="port-filter" id="port-filter">
            <button type="button" class="btn btn-sm btn-primary" data-filter="all">All ({{ $ports->count() }})</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="up">Up ({{ $up }})</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="down">Down ({{ $ports->count() - $up }})</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-filter="sfp">SFP only ({{ $sfp }})</button>
        </div>
    </div>

    <div class="card-body p-0 table-responsive">

        <table class="table table-sm table-hover port-table mb-0">
            <thead class="thead-light">
                <tr>
                    <th class="pl-3">Port</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Speed</th>
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
                    <tr data-state="{{ $isUp ? 'up' : 'down' }}" data-sfp="{{ $port->has_transceiver ? 1 : 0 }}"
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
                                @if ($port->rx_low_warn !== null)
                                    <div class="small text-muted font-weight-normal">min {{ $port->rx_low_warn }}</div>
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
                        <td colspan="11" class="text-center text-muted py-4">
                            No ports yet. Check the SNMP community and click <strong>Poll Now</strong>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>

    <div class="card-footer small text-muted">
        Rx colours use each module's own limits reported by the switch ("min" = low warning; hover for all limits):
        <span class="rx-good">normal</span> ·
        <span class="rx-warn">past warning</span> ·
        <span class="rx-bad">past alarm</span>.
        Modules without limits use
        @if ($rxThreshold === null)
            <span class="rx-warn">{{ $redAt }} to {{ $redAt + 3 }}</span> / <span class="rx-bad">&lt; {{ $redAt }} dBm</span>
            (set a fallback Low Rx threshold in Settings → Telegram to get alerts for them).
        @else
            the Settings → Telegram threshold: <span class="rx-bad">&lt; {{ $redAt }} dBm</span>.
        @endif
    </div>

</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Events</h3>
        <div class="card-tools">
            <a href="{{ route('switch-events.index', ['switch' => $switch->id]) }}" class="btn btn-tool">View all</a>
        </div>
    </div>
    <div class="card-body p-0">
        @include('switches._events_table', ['events' => $events, 'showSwitch' => false])
    </div>
</div>

@stop

@section('js')
<script>
(function () {
    var buttons = document.querySelectorAll('#port-filter [data-filter]');
    var rows = document.querySelectorAll('.port-table tbody tr[data-state]');

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var f = btn.dataset.filter;

            buttons.forEach(function (b) {
                b.classList.toggle('btn-primary', b === btn);
                b.classList.toggle('btn-outline-secondary', b !== btn);
            });

            rows.forEach(function (row) {
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

    // Keep port states fresh (the scheduler polls every minute).
    setTimeout(function () { location.reload(); }, 60000);
})();
</script>
@stop
