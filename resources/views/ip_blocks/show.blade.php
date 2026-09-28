@extends('adminlte::page')

@section('title', $block->cidr . ' — IP Block')

@php
    [$bMin, $bMax] = $block->range();
    $size = $block->size();
    $used = $block->usedCount($allocations);
    $pct = $size ? round($used / $size * 100, 1) : 0;
    $freeRows = collect($rows)->where('free', true);
    $largestFree = $freeRows->sortByDesc(fn ($r) => $r['end'] - $r['start'])->first();

    // One colour per device, stable across reloads.
    $palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#f97316', '#84cc16', '#06b6d4', '#e11d48', '#a855f7'];
    $colorFor = fn ($key) => $palette[abs(crc32(strtolower((string) $key))) % count($palette)];
    $showMap = $size <= 1024;
@endphp

@section('content_header')
<x-noc.header :title="$block->cidr" :back="route('ip-pools.index')"
    subtitle="{{ $block->name }} · {{ ucfirst($block->type) }} · {{ long2ip($bMin) }} – {{ long2ip($bMax) }}">
    <a href="{{ route('ip-blocks.edit', $block) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-edit"></i> Edit Block</a>
    <a href="{{ route('ip-pools.create', ['block' => $block->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Allocate Subnet</a>
</x-noc.header>
@stop

@section('css')
<style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .ipmap { display: grid; grid-template-columns: repeat(32, 1fr); gap: 2px; }
    @media (max-width: 767.98px) { .ipmap { grid-template-columns: repeat(16, 1fr); } }
    .ipmap a, .ipmap span { display: block; aspect-ratio: 1; border-radius: 3px; }
    .ipmap .free { background: #f1f5f9; outline: 1px dashed #cbd5e1; outline-offset: -1px; }
    .ipmap .free:hover { background: #dcfce7; outline-color: #22c55e; }
    .ipmap a:not(.free):hover { filter: brightness(1.15); transform: scale(1.25); position: relative; z-index: 1; }
    .ipmap-legend { display: flex; flex-wrap: wrap; gap: .35rem .9rem; font-size: .8rem; color: #475569; }
    .ipmap-legend i { display: inline-block; width: .7rem; height: .7rem; border-radius: 3px; margin-right: .3rem; vertical-align: -1px; }
    .ip-table td { vertical-align: middle; }
    .ip-table tr.free-row td { background: #f8fffb; color: #64748b; }
    .ip-table tr.free-row .mono { color: #16a34a; }
    .ip-swatch { display: inline-block; width: .55rem; height: .55rem; border-radius: 2px; margin-right: .45rem; }
    .vlan-chip { display: inline-block; margin: 0 .2rem .2rem 0; padding: .05rem .4rem; border-radius: .3rem; background: #eef2ff; color: #4338ca; font-size: .75rem; }
    .size-pill { display: inline-block; min-width: 2.6rem; padding: .1rem .45rem; border-radius: 999px; background: #f1f5f9; font-size: .75rem; text-align: center; color: #334155; }
</style>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#6366f1">
        <div class="acct-stat-label">Used <i class="fas fa-chart-pie"></i></div>
        <div class="acct-stat-value">{{ $pct }}%</div>
        <div class="acct-stat-foot">{{ number_format($used) }} of {{ number_format($size) }} addresses</div>
        <div class="acct-progress"><span style="width: {{ $pct }}%"></span></div>
    </div>
    <div class="acct-stat" style="--accent:#0ea5e9">
        <div class="acct-stat-label">Subnets <i class="fas fa-layer-group"></i></div>
        <div class="acct-stat-value">{{ $allocations->count() }}</div>
        <div class="acct-stat-foot">{{ $allocations->pluck('device')->filter()->unique()->count() }} devices</div>
    </div>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Free <i class="fas fa-check-circle"></i></div>
        <div class="acct-stat-value">{{ number_format($size - $used) }}</div>
        <div class="acct-stat-foot">in {{ $freeRows->count() }} {{ Str::plural('gap', $freeRows->count()) }}</div>
    </div>
    <div class="acct-stat" style="--accent:#f59e0b">
        <div class="acct-stat-label">Largest Free <i class="fas fa-expand"></i></div>
        <div class="acct-stat-value mono" style="font-size:1.15rem">{{ $largestFree['cidr'] ?? '—' }}</div>
        <div class="acct-stat-foot">{{ $largestFree ? ($largestFree['end'] - $largestFree['start'] + 1) . ' addresses' : 'Block is full' }}</div>
    </div>
</div>

@if ($showMap)
    <div class="card acct-panel">
        <div class="card-header">
            <h3 class="card-title">Address Map</h3>
            <span class="small text-muted">each square = 1 IP · click to open · dashed = free</span>
        </div>
        <div class="card-body">
            <div class="ipmap">
                @foreach ($rows as $row)
                    @for ($ip = $row['start']; $ip <= $row['end']; $ip++)
                        @if ($row['free'])
                            <a href="{{ route('ip-pools.create', ['block' => $block->id, 'subnet' => $row['cidr']]) }}" class="free"
                               title="{{ long2ip($ip) }} — free ({{ $row['cidr'] }}), click to allocate"></a>
                        @else
                            @php $p = $row['pool']; @endphp
                            <a href="{{ route('ip-pools.edit', $p) }}" style="background: {{ $colorFor($p->device ?: $p->subnet) }}"
                               title="{{ long2ip($ip) }} — {{ $p->subnet }} · {{ $p->device ?: 'no device' }}{{ $p->purpose ? ' · ' . $p->purpose : '' }}"></a>
                        @endif
                    @endfor
                @endforeach
            </div>
            <div class="ipmap-legend mt-3">
                @foreach ($allocations->pluck('device')->filter()->unique()->sort() as $device)
                    <span><i style="background: {{ $colorFor($device) }}"></i>{{ $device }}</span>
                @endforeach
                <span><i style="background:#f1f5f9; outline:1px dashed #cbd5e1"></i>Free</span>
            </div>
        </div>
    </div>
@endif

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title">Subnets</h3>
        <div class="d-flex align-items-center" style="gap:.5rem">
            <div class="custom-control custom-switch mr-2">
                <input type="checkbox" class="custom-control-input" id="show-free" checked>
                <label class="custom-control-label small" for="show-free">Show free</label>
            </div>
            <input type="search" id="ip-search" class="form-control form-control-sm" style="width: 220px" placeholder="Search IP, device, VLAN…">
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover ip-table mb-0" id="ip-table">
            <thead>
                <tr>
                    <th class="pl-3">Subnet</th>
                    <th>Size</th>
                    <th>Usable Range</th>
                    <th>Device</th>
                    <th>Purpose</th>
                    <th>Private IP (PPPoE)</th>
                    <th>Pairing VLAN</th>
                    <th class="text-right pr-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @if ($row['free'])
                        @php $n = $row['end'] - $row['start'] + 1; @endphp
                        <tr class="free-row" data-search="{{ $row['cidr'] }} free">
                            <td class="pl-3 mono">{{ $row['cidr'] }}</td>
                            <td><span class="size-pill">/{{ 32 - (int) log($n, 2) }}</span></td>
                            <td class="mono small">{{ long2ip($row['start']) }} – {{ long2ip($row['end']) }}</td>
                            <td colspan="4"><i class="fas fa-circle text-success mr-1" style="font-size:.5rem"></i> Free · {{ $n }} {{ Str::plural('address', $n) }}</td>
                            <td class="text-right pr-3">
                                <a href="{{ route('ip-pools.create', ['block' => $block->id, 'subnet' => $row['cidr']]) }}" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-plus"></i> Allocate
                                </a>
                            </td>
                        </tr>
                    @else
                        @php
                            $p = $row['pool'];
                            $d = $p->details();
                            $dup = $p->private_subnet && ($privateCounts[$p->private_subnet] ?? 0) > 1;
                        @endphp
                        <tr data-search="{{ strtolower($p->subnet . ' ' . $p->device . ' ' . $p->purpose . ' ' . $p->private_subnet . ' ' . $p->vlan . ' ' . $p->description) }}">
                            <td class="pl-3 mono font-weight-bold">
                                <span class="ip-swatch" style="background: {{ $colorFor($p->device ?: $p->subnet) }}"></span>{{ $p->subnet }}
                            </td>
                            <td><span class="size-pill">/{{ $d['prefix'] }}</span> <small class="text-muted">{{ $d['size'] }}</small></td>
                            <td class="mono small">{{ $d['first'] }} – {{ $d['last'] }}</td>
                            <td class="font-weight-bold" style="color:#0f172a">{{ $p->device ?: '—' }}</td>
                            <td>{{ $p->purpose ?: '—' }}</td>
                            <td class="mono small">
                                {{ $p->private_subnet ?: '—' }}
                                @if ($dup) <i class="fas fa-exclamation-triangle text-warning" title="This private range is also used by another subnet"></i> @endif
                            </td>
                            <td>
                                @foreach (array_filter(array_map('trim', explode(',', (string) $p->vlan))) as $v)
                                    <span class="vlan-chip mono">{{ $v }}</span>
                                @endforeach
                                @if (! $p->vlan) <span class="text-muted">—</span> @endif
                            </td>
                            <td class="text-right pr-3 text-nowrap">
                                <a href="{{ route('ip-pools.edit', $p) }}" class="btn btn-sm btn-light" title="Edit"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('ip-pools.destroy', $p) }}" method="POST" class="d-inline js-confirm-delete"
                                      data-confirm-message="Release {{ $p->subnet }} ({{ $p->device }})? It will show as free.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger" title="Release"><i class="fas fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($block->description)
    <div class="text-muted small mb-3"><i class="fas fa-info-circle"></i> {{ $block->description }}</div>
@endif

@stop

@section('js')
<script>
(function () {
    var search = document.getElementById('ip-search');
    var showFree = document.getElementById('show-free');
    var rows = document.querySelectorAll('#ip-table tbody tr');

    function apply() {
        var q = search.value.trim().toLowerCase();
        rows.forEach(function (tr) {
            var isFree = tr.classList.contains('free-row');
            var match = !q || tr.dataset.search.indexOf(q) !== -1;
            tr.hidden = !match || (isFree && !showFree.checked);
        });
    }

    search.addEventListener('input', apply);
    showFree.addEventListener('change', apply);
})();
</script>
@stop
