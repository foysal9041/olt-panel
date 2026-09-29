@extends('adminlte::page')

@section('title', 'NOC Overview')

@php
    $pct = fn ($part, $whole) => $whole > 0 ? round($part / $whole * 100) : 0;
    $oltOnline = $olts->where('status', 1)->count();
    $swUp = $switches->where('status', 1)->count();
    $latOk = $latencyTargets->filter(fn ($t) => in_array($t->status, ['up', 'degraded']))->count();
    $dangerCount = collect($issues)->where(0, 'danger')->count();
    $canManageOlt = Gate::allows('access-olt-manage');
    $canSwitches = Gate::allows('access-olt-switches');
    $canLatency = Gate::allows('access-latency-graphs');
    $eventColors = ['danger' => '#ef4444', 'success' => '#22c55e', 'warning' => '#f59e0b', 'info' => '#0ea5e9'];
@endphp

@section('content_header')
<x-noc.header title="NOC Overview" icon="fas fa-satellite-dish" subtitle="Live network health — OLTs, switches, links and latency">
    <span class="dash-live {{ $issues ? 'dash-live--bad' : '' }}">
        <span class="dash-health-dot"></span>
        {{ $issues ? count($issues) . ' ' . Str::plural('issue', count($issues)) : 'All systems normal' }}
    </span>
    @if ($canManageOlt)
        <a href="{{ route('olt.create') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-plus"></i> OLT</a>
    @endif
    @if ($canSwitches)
        <a href="{{ route('switches.create') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-plus"></i> Switch</a>
    @endif
</x-noc.header>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('css/latency.css') }}?v={{ filemtime(public_path('css/latency.css')) }}">
<style>
    .dash-live { display: inline-flex; align-items: center; gap: .45rem; padding: .3rem .8rem; border-radius: 999px;
        background: #dcfce7; color: #15803d; font-size: .82rem; font-weight: 600; }
    .dash-live--bad { background: #ffe4e6; color: #be123c; }
    .dash-live .dash-health-dot { width: .5rem; height: .5rem; border-radius: 50%; background: #22c55e; }
    .dash-live--bad .dash-health-dot { background: #f43f5e; animation: dash-pulse 1.6s ease-in-out infinite; }

    /* OLTs grouped by zone: one compact tile per zone, one dot per OLT */
    .oz-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .oz-tools .form-control { width: 210px; max-width: 100%; }
    .oz-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .65rem; border-radius: 999px; font-size: .78rem; font-weight: 600; }
    .oz-pill.up { background: #dcfce7; color: #15803d; }
    .oz-pill.down { background: #ffe4e6; color: #be123c; }
    .oz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .7rem; }
    .oz-tile { position: relative; padding: .65rem .8rem .55rem; border-radius: .7rem; background: #fff; border: 1px solid #eef2f7; }
    .oz-tile::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 3px; border-radius: .7rem 0 0 .7rem; background: #22c55e; }
    .oz-tile.has-down { background: #fff7f8; border-color: #fecdd3; }
    .oz-tile.has-down::before { background: #f43f5e; }
    .oz-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; }
    .oz-name { font-weight: 700; color: #0f172a; font-size: .88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oz-count { font-size: .78rem; font-weight: 700; color: #15803d; white-space: nowrap; }
    .oz-tile.has-down .oz-count { color: #be123c; }
    .oz-dots { display: flex; flex-wrap: wrap; gap: 4px; margin: .45rem 0 .15rem; }
    .oz-dot { width: 13px; height: 13px; border-radius: 3px; background: #22c55e; display: inline-block; }
    .oz-dot.down { background: #f43f5e; animation: dash-pulse 1.6s ease-in-out infinite; }
    a.oz-dot:hover { outline: 2px solid #0f172a33; }
    .oz-down { font-size: .75rem; color: #be123c; line-height: 1.3; }
    .oz-more summary { font-size: .74rem; color: #64748b; cursor: pointer; list-style: none; margin-top: .2rem; }
    .oz-more summary::-webkit-details-marker { display: none; }
    .oz-more summary::before { content: '\25B8'; display: inline-block; margin-right: .3rem; transition: transform .15s; }
    .oz-more[open] summary::before { transform: rotate(90deg); }
    .oz-list { list-style: none; padding: 0; margin: .4rem 0 0; border-top: 1px dashed #e2e8f0; }
    .oz-list li { display: flex; align-items: center; gap: .4rem; padding: .3rem 0; font-size: .78rem; border-bottom: 1px dashed #f1f5f9; }
    .oz-list li:last-child { border-bottom: 0; }
    .oz-list .oz-dot { width: 9px; height: 9px; flex: none; animation: none; }
    .oz-list .oz-olt { flex: 1; min-width: 0; }
    .oz-list .oz-olt b { display: block; color: #0f172a; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oz-list .oz-olt code { font-size: .72rem; color: #64748b; background: none; padding: 0; }
    .oz-list .oz-act a { color: #94a3b8; margin-left: .3rem; }
    .oz-list .oz-act a:hover { color: #4f46e5; }
    .oz-empty { display: none; padding: 1.5rem; text-align: center; color: #64748b; }

    .sw-bar { height: .4rem; border-radius: 999px; background: #eef2f7; overflow: hidden; min-width: 70px; }
    .sw-bar span { display: block; height: 100%; background: #0ea5e9; border-radius: 999px; }
    .sw-table td { vertical-align: middle; }
    .sw-dot { display: inline-block; width: .6rem; height: .6rem; border-radius: 50%; margin-right: .35rem; }

    .inv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: .85rem; }
    .inv-tile { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: .8rem; background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .08); color: inherit; }
    .inv-tile:hover { color: inherit; text-decoration: none; box-shadow: 0 8px 18px -8px rgba(15, 23, 42, .25); }
    .inv-tile b { display: block; font-size: 1.3rem; color: #0f172a; line-height: 1.1; }
    .inv-tile small { color: #64748b; }

    .lat-mini { padding: .75rem .9rem .5rem; border-radius: .8rem; background: #fff; border: 1px solid #eef2f7; }
    .lat-mini.alert-on { border-color: #fecaca; box-shadow: 0 0 0 1px #fecaca; }
    .lat-mini-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; }
    .lat-mini-head a { font-weight: 600; color: #0f172a; }
</style>
@stop

@section('content')

{{-- ============ KPIs ============ --}}
<div class="kpi-grid">

    <a href="{{ $canManageOlt ? route('olt.index') : '#olts' }}" class="kpi">
        <div class="kpi-top"><span class="kpi-label">OLTs Online</span><span class="kpi-icon tone-indigo"><i class="fas fa-network-wired"></i></span></div>
        <div class="kpi-value">{{ $oltOnline }} <small>/ {{ $olts->count() }}</small></div>
        <div class="kpi-foot">
            @if ($olts->count() - $oltOnline) <span class="text-danger font-weight-bold">{{ $olts->count() - $oltOnline }} offline</span>
            @else All online @endif
        </div>
        <div class="kpi-bar"><span class="{{ $oltOnline < $olts->count() ? 'fill-rose' : 'fill-green' }}" style="width: {{ $pct($oltOnline, $olts->count()) }}%"></span></div>
    </a>

    @if ($canSwitches)
        <a href="{{ route('switches.index') }}" class="kpi">
            <div class="kpi-top"><span class="kpi-label">Switches Up</span><span class="kpi-icon tone-sky"><i class="fas fa-server"></i></span></div>
            <div class="kpi-value">{{ $swUp }} <small>/ {{ $switches->count() }}</small></div>
            <div class="kpi-foot">
                @if ($switches->count() - $swUp) <span class="text-danger font-weight-bold">{{ $switches->count() - $swUp }} not responding</span>
                @else All responding @endif
            </div>
            <div class="kpi-bar"><span class="{{ $swUp < $switches->count() ? 'fill-rose' : 'fill-sky' }}" style="width: {{ $pct($swUp, $switches->count()) }}%"></span></div>
        </a>

        <a href="{{ route('switches.index') }}" class="kpi">
            <div class="kpi-top"><span class="kpi-label">Ports Up</span><span class="kpi-icon tone-green"><i class="fas fa-plug"></i></span></div>
            <div class="kpi-value">{{ $ports['up'] }} <small>/ {{ $ports['total'] }}</small></div>
            <div class="kpi-foot">{{ $ports['total'] - $ports['up'] }} down or unused</div>
            <div class="kpi-bar"><span class="fill-green" style="width: {{ $pct($ports['up'], $ports['total']) }}%"></span></div>
        </a>

        <a href="{{ route('switches.index') }}" class="kpi">
            <div class="kpi-top"><span class="kpi-label">SFP Modules</span><span class="kpi-icon tone-amber"><i class="fas fa-lightbulb"></i></span></div>
            <div class="kpi-value">{{ $ports['sfp'] }}</div>
            <div class="kpi-foot">
                @if ($ports['rx_alarms']) <span class="text-warning font-weight-bold">{{ $ports['rx_alarms'] }} low Rx</span>
                @else All Rx within limits @endif
            </div>
            <div class="kpi-bar"><span class="{{ $ports['rx_alarms'] ? 'fill-amber' : 'fill-green' }}" style="width: {{ $ports['sfp'] ? 100 - $pct($ports['rx_alarms'], $ports['sfp']) : 0 }}%"></span></div>
        </a>
    @endif

    @if ($canLatency)
        <a href="{{ route('latency.index') }}" class="kpi">
            <div class="kpi-top"><span class="kpi-label">Latency OK</span><span class="kpi-icon tone-violet"><i class="fas fa-wave-square"></i></span></div>
            <div class="kpi-value">{{ $latOk }} <small>/ {{ $latencyTargets->count() }}</small></div>
            <div class="kpi-foot">
                @if ($latencyTargets->count() - $latOk) <span class="text-danger font-weight-bold">{{ $latencyTargets->count() - $latOk }} over threshold / down</span>
                @else All within limits @endif
            </div>
            <div class="kpi-bar"><span class="{{ $latOk < $latencyTargets->count() ? 'fill-amber' : 'fill-indigo' }}" style="width: {{ $pct($latOk, $latencyTargets->count()) }}%"></span></div>
        </a>
    @endif

    @if ($events24h !== null)
        <a href="{{ route('switch-events.index') }}" class="kpi">
            <div class="kpi-top"><span class="kpi-label">Events (24h)</span><span class="kpi-icon tone-rose"><i class="fas fa-history"></i></span></div>
            <div class="kpi-value">{{ $events24h }}</div>
            <div class="kpi-foot">Port up/down, reachability, Rx</div>
        </a>
    @endif

</div>

{{-- ============ Attention + events ============ --}}
<div class="row">
    <div class="{{ $canSwitches ? 'col-lg-7' : 'col-12' }}">
        <div class="card dash-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bell mr-1 text-danger"></i> Needs Attention</h3>
                @if ($issues)
                    <span class="badge {{ $dangerCount ? 'badge-danger' : 'badge-warning' }}">{{ count($issues) }}</span>
                @endif
            </div>
            <div class="card-body p-0" style="max-height: 360px; overflow-y: auto;">
                @forelse ($issues as [$severity, $icon, $title, $detail, $url])
                    <a href="{{ $url }}" class="issue">
                        <span class="issue-icon {{ $severity === 'danger' ? 'tone-rose' : 'tone-amber' }}"><i class="{{ $icon }}"></i></span>
                        <span>
                            <div class="issue-title">{{ $title }}</div>
                            @if ($detail) <div class="issue-detail">{{ $detail }}</div> @endif
                        </span>
                    </a>
                @empty
                    <div class="dash-empty">
                        <i class="fas fa-check-circle"></i>
                        <strong>Everything looks good.</strong><br>
                        No offline devices, down links or threshold alerts right now.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($canSwitches)
        <div class="col-lg-5">
            <div class="card dash-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Recent Events</h3>
                    <a href="{{ route('switch-events.index') }}" class="small">View all</a>
                </div>
                <div class="card-body p-0" style="max-height: 360px; overflow-y: auto;">
                    @if ($events->isEmpty())
                        <div class="dash-empty"><i class="fas fa-stream" style="color:#cbd5e1"></i>No port or switch events yet.</div>
                    @else
                        <ul class="timeline-mini">
                            @foreach ($events as $event)
                                @php [$label, $color] = \App\Models\SwitchEvent::TYPES[$event->type] ?? [$event->type, 'secondary']; @endphp
                                <li style="--dot: {{ $eventColors[$color] ?? '#94a3b8' }}">
                                    <div class="tl-time">{{ $event->occurred_at->format('d M, h:i A') }} · {{ $event->occurred_at->diffForHumans() }}</div>
                                    <div class="tl-text"><strong>{{ $label }}</strong> — {{ $event->message }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

{{-- ============ OLTs by zone ============ --}}
@php
    // One tile per zone; zones with an offline OLT first.
    $oltZones = $olts->groupBy(fn ($o) => $o->zone ?: 'No zone')
        ->map(fn ($list, $zone) => [
            'zone' => $zone,
            'olts' => $list->sortBy([
                fn ($a, $b) => $a->status <=> $b->status,
                fn ($a, $b) => strnatcasecmp($a->name, $b->name),
            ])->values(),
            'down' => $list->where('status', 0)->count(),
        ])
        ->sortBy(fn ($z) => [$z['down'] ? 0 : 1, strtolower($z['zone'])])
        ->values();
    $oltOffline = $olts->count() - $oltOnline;
@endphp
<div class="card dash-panel" id="olts">
    <div class="card-header d-flex flex-wrap align-items-center" style="gap:.5rem">
        <h3 class="card-title mr-auto"><i class="fas fa-network-wired mr-1" style="color:#4f46e5"></i> OLTs by Zone
            <small class="text-muted ml-1">{{ $oltZones->count() }} zones</small>
        </h3>
        <div class="oz-tools">
            <span class="oz-pill up"><i class="fas fa-circle" style="font-size:.5rem"></i> {{ $oltOnline }} online</span>
            @if ($oltOffline)
                <button type="button" class="oz-pill down border-0" id="oz-offline" title="Show only zones with an offline OLT">
                    <i class="fas fa-circle" style="font-size:.5rem"></i> {{ $oltOffline }} offline
                </button>
            @endif
            <input type="search" id="oz-search" class="form-control form-control-sm" placeholder="Search zone, OLT or IP…">
            @if ($canManageOlt)
                <a href="{{ route('olt.index') }}" class="small ml-1">Manage</a>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if ($olts->isEmpty())
            <div class="dash-empty"><i class="fas fa-network-wired" style="color:#cbd5e1"></i>No OLTs yet.</div>
        @else
            <div class="oz-grid" id="oz-grid">
                @foreach ($oltZones as $z)
                    <div class="oz-tile {{ $z['down'] ? 'has-down' : '' }}" data-zone="{{ $z['zone'] }}" data-down="{{ $z['down'] }}"
                         data-search="{{ strtolower($z['zone'] . ' ' . $z['olts']->map(fn ($o) => $o->name . ' ' . $o->ip . ' ' . $o->vlan)->implode(' ')) }}">
                        <div class="oz-head">
                            <span class="oz-name" title="{{ $z['zone'] }}">{{ $z['zone'] }}</span>
                            <span class="oz-count">{{ $z['olts']->count() - $z['down'] }}/{{ $z['olts']->count() }}</span>
                        </div>
                        <div class="oz-dots">
                            @foreach ($z['olts'] as $olt)
                                @php $tip = $olt->name . ' · ' . $olt->ip . ($olt->vlan ? ' · VLAN ' . $olt->vlan : '') . ' · ' . ($olt->status ? 'LIVE' : 'DOWN'); @endphp
                                @if ($canManageOlt)
                                    <a href="{{ route('olt.show', $olt) }}" class="oz-dot {{ $olt->status ? '' : 'down' }}" title="{{ $tip }}"></a>
                                @else
                                    <span class="oz-dot {{ $olt->status ? '' : 'down' }}" title="{{ $tip }}"></span>
                                @endif
                            @endforeach
                        </div>
                        @if ($z['down'])
                            <div class="oz-down"><i class="fas fa-exclamation-circle"></i> {{ $z['olts']->where('status', 0)->pluck('name')->implode(', ') }}</div>
                        @endif
                        <details class="oz-more">
                            <summary>{{ $z['olts']->count() }} {{ Str::plural('OLT', $z['olts']->count()) }}</summary>
                            <ul class="oz-list">
                                @foreach ($z['olts'] as $olt)
                                    <li>
                                        <span class="oz-dot {{ $olt->status ? '' : 'down' }}"></span>
                                        <span class="oz-olt">
                                            <b title="{{ $olt->name }}">{{ $olt->name }}</b>
                                            <code>{{ $olt->ip }}</code>@if ($olt->vlan) <span class="text-muted">· VLAN {{ $olt->vlan }}</span>@endif
                                        </span>
                                        @if ($canManageOlt)
                                            <span class="oz-act text-nowrap">
                                                <a href="{{ route('olt.show', $olt) }}" title="Details"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('olt.web', $olt) }}" target="_blank" title="Web"><i class="fas fa-external-link-alt"></i></a>
                                                <a href="{{ route('olts.ping', $olt) }}" target="_blank" title="Ping"><i class="fas fa-satellite-dish"></i></a>
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    </div>
                @endforeach
            </div>
            <div class="oz-empty" id="oz-empty"><i class="fas fa-search"></i> No zone or OLT matches.</div>
        @endif
    </div>
</div>

{{-- ============ Switches ============ --}}
@if ($canSwitches && $switches->isNotEmpty())
    <div class="card dash-panel">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-server mr-1" style="color:#0284c7"></i> Switch Health</h3>
            <a href="{{ route('switches.index') }}" class="small">All switches</a>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover sw-table mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">Switch</th>
                        <th>Vendor</th>
                        <th style="min-width: 150px">Ports Up</th>
                        <th>SFP</th>
                        <th>Uptime</th>
                        <th class="pr-3">Last Poll</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($switches as $sw)
                        <tr>
                            <td class="pl-3">
                                <span class="sw-dot" style="background: {{ $sw->status === 1 ? '#22c55e' : ($sw->status === 0 ? '#f43f5e' : '#cbd5e1') }}"></span>
                                <a href="{{ route('switches.show', $sw) }}" class="font-weight-bold" style="color:#0f172a">{{ $sw->name }}</a>
                                <div class="small text-muted" style="margin-left: .95rem">{{ $sw->ip }}{{ $sw->zone ? ' · ' . $sw->zone : '' }}</div>
                            </td>
                            <td>{{ $sw->vendor_label }}</td>
                            <td>
                                <div class="d-flex align-items-center" style="gap:.5rem">
                                    <div class="sw-bar flex-grow-1"><span style="width: {{ $pct($sw->ports_up_count, $sw->ports_count) }}%"></span></div>
                                    <span class="small text-nowrap">{{ $sw->ports_up_count }}/{{ $sw->ports_count }}</span>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                {{ $sw->ports_sfp_count }}
                                @if ($sw->ports_rx_alarm_count)
                                    <span class="badge badge-warning ml-1"><i class="fas fa-exclamation-triangle"></i> {{ $sw->ports_rx_alarm_count }}</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $sw->uptime_human ?? '—' }}</td>
                            <td class="pr-3 text-nowrap small text-muted">{{ $sw->last_polled_at?->diffForHumans() ?? 'never' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- ============ Latency ============ --}}
@if ($canLatency && $latencyTargets->isNotEmpty())
    <div class="card dash-panel">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-wave-square mr-1" style="color:#7c3aed"></i> Latency — last 3 hours</h3>
            <a href="{{ route('latency.index') }}" class="small">All graphs</a>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach ($latencyTargets->take(6) as $t)
                    <div class="col-xl-4 col-md-6 mb-3">
                        <div class="lat-mini {{ $t->alert_active ? 'alert-on' : '' }}">
                            <div class="lat-mini-head">
                                <a href="{{ route('latency.show', $t) }}">{{ $t->name }}</a>
                                <span class="small {{ $t->alert_active ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                    {{ $t->last_median !== null ? round($t->last_median, 1) . ' ms' : '—' }}
                                    @if ($t->last_loss > 0) · {{ round($t->last_loss) }}% loss @endif
                                </span>
                            </div>
                            <div class="js-smokegraph" data-url="{{ route('latency.data', ['target' => $t, 'range' => '3h', 'points' => 100]) }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($latencyTargets->count() > 6)
                <div class="text-center small"><a href="{{ route('latency.index') }}">+ {{ $latencyTargets->count() - 6 }} more targets</a></div>
            @endif
        </div>
    </div>
@endif

{{-- ============ Inventory ============ --}}
@if ($inventory)
    <div class="inv-grid mb-3">
        @foreach ($inventory as [$label, $count, $icon, $route])
            <a href="{{ route($route) }}" class="inv-tile">
                <span class="kpi-icon tone-indigo"><i class="{{ $icon }}"></i></span>
                <span><b>{{ $count }}</b><small>{{ $label }}</small></span>
            </a>
        @endforeach
    </div>
@endif

@stop

@section('js')
<script src="{{ asset('js/smokegraph.js') }}?v={{ filemtime(public_path('js/smokegraph.js')) }}"></script>
<script>
document.querySelectorAll('.js-smokegraph').forEach(function (el) {
    SmokeGraph.create(el, { url: el.dataset.url, height: 110, compact: true, refresh: 60 });
});

// OLTs by zone: search, "offline only", and open lists survive the reload.
(function () {
    var grid = document.getElementById('oz-grid');
    if (!grid) return;
    var tiles = Array.prototype.slice.call(grid.querySelectorAll('.oz-tile'));
    var search = document.getElementById('oz-search');
    var offlineBtn = document.getElementById('oz-offline');
    var store = {
        get: function (k) { try { return JSON.parse(sessionStorage.getItem('noc-oz-' + k)); } catch (e) { return null; } },
        set: function (k, v) { try { sessionStorage.setItem('noc-oz-' + k, JSON.stringify(v)); } catch (e) {} }
    };
    var offlineOnly = !!store.get('offline') && !!offlineBtn;
    search.value = store.get('q') || '';

    function apply() {
        var q = search.value.trim().toLowerCase(), shown = 0;
        tiles.forEach(function (t) {
            var ok = (!q || t.dataset.search.indexOf(q) !== -1) && (!offlineOnly || t.dataset.down !== '0');
            t.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        document.getElementById('oz-empty').style.display = shown ? 'none' : 'block';
        if (offlineBtn) offlineBtn.style.boxShadow = offlineOnly ? '0 0 0 2px #be123c' : '';
        store.set('q', search.value);
        store.set('offline', offlineOnly);
    }

    search.addEventListener('input', apply);
    if (offlineBtn) offlineBtn.addEventListener('click', function () { offlineOnly = !offlineOnly; apply(); });

    var open = store.get('open') || [];
    tiles.forEach(function (t) {
        var d = t.querySelector('details');
        if (open.indexOf(t.dataset.zone) !== -1) d.open = true;
        d.addEventListener('toggle', function () {
            var list = tiles.filter(function (x) { return x.querySelector('details').open; }).map(function (x) { return x.dataset.zone; });
            store.set('open', list);
        });
    });

    apply();
})();

// OLT status is checked every 30 seconds; keep the page in step.
setTimeout(function () { location.reload(); }, 30000);
</script>
@stop
