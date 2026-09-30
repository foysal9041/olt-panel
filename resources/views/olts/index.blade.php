@extends('adminlte::page')

@section('title', 'OLTs')

@php
    $isAdmin = strtolower(auth()->user()->role) === 'admin';
    $online = $olts->where('status', 1)->count();
    $offline = $olts->count() - $online;
    $filtered = request()->hasAny(['zone', 'search', 'vlan', 'status']) && collect(request()->only(['zone', 'search', 'vlan', 'status']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('content_header')
<x-noc.header title="OLTs" icon="fas fa-network-wired" subtitle="Every OLT — status, IP, VLAN, web access and credentials">
    <a href="{{ route('olt.create', array_filter(['zone' => request('zone')])) }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> {{ request('zone') ? 'Add OLTs to ' . request('zone') : 'Add OLTs' }}
    </a>
</x-noc.header>
@stop

@section('css')
<style>
    .olt-filter .form-control, .olt-filter .btn { height: calc(1.5em + .75rem + 2px); }
    .olt-filter label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-bottom: .25rem; }
    .olt-table td { vertical-align: middle; }
    .olt-name { font-weight: 600; color: #0f172a; }
    .olt-sub { font-size: .76rem; color: #94a3b8; }
    .olt-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; color: #334155; }
    .olt-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; margin-right: .3rem; vertical-align: middle; background: currentColor; }
    .olt-actions .btn { padding: .2rem .45rem; }
</style>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="acct-stats" data-live="olt-stats">
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">{{ $filtered ? 'Matching OLTs' : 'OLTs' }} <i class="fas fa-network-wired"></i></div>
        <div class="acct-stat-value">{{ $olts->count() }}</div>
        <div class="acct-stat-foot">in {{ $olts->pluck('zone')->filter()->unique()->count() }} zones</div>
    </div>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Online <i class="fas fa-check-circle"></i></div>
        <div class="acct-stat-value">{{ $online }}</div>
        <div class="acct-progress"><span style="width: {{ $olts->count() ? round($online / $olts->count() * 100) : 0 }}%; background:#16a34a"></span></div>
    </div>
    <a href="{{ route('olt.index', array_merge(request()->only(['zone', 'search', 'vlan']), ['status' => 0])) }}" class="acct-stat" style="--accent:#e11d48; color:inherit; text-decoration:none">
        <div class="acct-stat-label">Offline <i class="fas fa-times-circle"></i></div>
        <div class="acct-stat-value {{ $offline ? 'text-danger' : '' }}">{{ $offline }}</div>
        <div class="acct-stat-foot">{{ $offline ? 'Show only offline' : 'All reachable' }}</div>
    </a>
</div>

<div class="card acct-panel">
    <div class="card-body pb-2">
        <form method="GET" action="{{ route('olt.index') }}" class="form-row align-items-end olt-filter">
            <div class="col-md-4 col-lg-3 form-group">
                <label>Zone</label>
                <select name="zone" class="form-control js-zone-select" data-placeholder="All zones ({{ $zoneStats->sum('count') }} OLTs)" onchange="this.form.submit()">
                    <option value=""></option>
                    @foreach ($zoneStats as $zoneName => $st)
                        <option value="{{ $zoneName }}" data-name="{{ $zoneName }}" data-count="{{ $st['count'] }}" data-down="{{ $st['down'] }}" data-pop="{{ $st['pop'] ? 1 : 0 }}" @selected(request('zone') === $zoneName)>{{ $zoneName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-3 form-group">
                <label>Name / IP</label>
                <input type="text" name="search" class="form-control" placeholder="e.g. Navaron or 192.168.50" value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>VLAN</label>
                <input type="text" name="vlan" class="form-control" placeholder="e.g. 1233" value="{{ request('vlan') }}">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>Status</label>
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="1" @selected(request('status') === '1')>Online</option>
                    <option value="0" @selected(request('status') === '0')>Offline</option>
                </select>
            </div>
            <div class="col-md-12 col-lg-2 form-group d-flex" style="gap:.35rem">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-search"></i> Filter</button>
                @if ($filtered)
                    <a href="{{ route('olt.index') }}" class="btn btn-light" title="Clear filters"><i class="fas fa-times"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card acct-panel">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover olt-table data-table mb-0" data-live-table="olts">
                <thead>
                    <tr>
                        <th class="text-center" style="width:3rem" data-orderable="false" data-searchable="false">SL</th>
                        <th>OLT</th>
                        <th>Zone</th>
                        <th>IP address</th>
                        <th>VLAN</th>
                        <th>Status</th>
                        <th class="text-right pr-3" data-orderable="false">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($olts as $olt)
                        <tr data-key="{{ $olt->id }}">
                            <td class="text-center text-muted" data-live-ignore>{{ $loop->iteration }}</td>
                            <td>
                                <div class="olt-name">{{ $olt->name }}</div>
                                <div class="olt-sub">{{ $olt->brand ?: 'Brand not set' }}</div>
                            </td>
                            <td class="small">{{ $olt->zone }}</td>
                            <td class="olt-mono" data-order="{{ sprintf('%010u', ip2long($olt->ip) ?: 0) }}">{{ $olt->ip }}</td>
                            <td class="olt-mono">{{ $olt->vlan ?: '—' }}</td>
                            <td data-order="{{ $olt->status }}">
                                @if ($olt->status == 1)
                                    <span class="badge badge-success"><span class="olt-dot"></span>Online</span>
                                @else
                                    <span class="badge badge-danger"><span class="olt-dot"></span>Offline</span>
                                @endif
                            </td>
                            <td class="text-right pr-3 text-nowrap olt-actions">
                                <a href="{{ route('olt.web', $olt->id) }}" target="_blank" class="btn btn-light btn-sm" title="Open web interface"><i class="fas fa-globe text-info"></i></a>
                                <a href="{{ route('olts.ping', $olt->id) }}" target="_blank" class="btn btn-light btn-sm" title="Ping"><i class="fas fa-satellite-dish text-success"></i></a>
                                @if ($isAdmin)
                                    <a href="{{ route('olt.show', $olt->id) }}" class="btn btn-light btn-sm" title="Details &amp; credentials"><i class="fas fa-key text-warning"></i></a>
                                    <a href="{{ route('olt.edit', $olt->id) }}" class="btn btn-light btn-sm" title="Edit"><i class="fas fa-pen text-primary"></i></a>
                                    <form action="{{ route('olt.destroy', $olt->id) }}" method="POST" class="d-inline js-confirm-delete"
                                          data-confirm-message="Delete OLT {{ $olt->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-light btn-sm" title="Delete"><i class="fas fa-trash text-danger"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@stop

@section('js')
<script>
// Status updates every 30s in place — filters, search and paging stay put.
LiveRefresh.start(30000);

// SL numbers follow the table's current order/search/page, 1..n.
$(function () {
    setTimeout(function () {
        var $table = $('table.data-table');
        if (!$.fn.dataTable || !$.fn.dataTable.isDataTable($table)) return;

        var dt = $table.DataTable();
        dt.on('draw.dt', function () {
            var start = dt.page.info().start;
            dt.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, i) {
                cell.textContent = start + i + 1;
            });
        }).draw(false);
    }, 0);
});
</script>
@stop
