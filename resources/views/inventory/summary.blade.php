@extends('adminlte::page')

@section('title', 'Inventory & Assets')

@use('App\Support\Dec')
@use('App\Services\InventoryStock')
@use('App\Models\InventoryMovement')
@php
    $tk = fn ($v) => '৳' . preg_replace('/\.00$/', '', Dec::lakh(round((float) $v, 2)));
    $qty = fn ($v) => InventoryStock::qty($v);
    $catTotal = array_sum($s['by_category']) ?: 1;
    $palette = ['#4f46e5', '#0ea5e9', '#16a34a', '#d97706', '#e11d48', '#7c3aed', '#0f766e', '#64748b', '#ca8a04', '#be185d'];
    $profit = $s['month_sales'] - $s['month_sales_cost'];
@endphp

@section('content_header')
<x-inventory.header title="Inventory & Assets" icon="fas fa-boxes" subtitle="What the company owns, where it is, and what was bought, used and sold">
    <a href="{{ route('inventory.summary', ['print' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print summary</a>
    @can('access-inventory-stock')
        <a href="{{ route('inventory.entries.create', ['type' => 'purchase']) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Stock entry</a>
    @endcan
</x-inventory.header>
@stop

@section('content')

<div class="acct-stats">
    <div class="acct-stat acct-stat-hero">
        <div class="acct-stat-label">Company assets <i class="fas fa-building"></i></div>
        <div class="acct-stat-value">{{ $tk($s['company_assets']) }}</div>
        <div class="acct-stat-foot">In the store + equipment in use</div>
    </div>
    <div class="acct-stat" style="--accent:#0284c7">
        <div class="acct-stat-label">In the store <i class="fas fa-warehouse"></i></div>
        <div class="acct-stat-value">{{ $tk($s['store_value']) }}</div>
        <div class="acct-stat-foot">{{ $s['items_in_stock'] }} of {{ $s['items'] }} products in stock</div>
    </div>
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">In use <i class="fas fa-network-wired"></i></div>
        <div class="acct-stat-value">{{ $tk($s['in_use_value']) }}</div>
        <div class="acct-stat-foot">At {{ $s['locations']->count() }} {{ \App\Support\Ui::t(Str::plural('place', $s['locations']->count())) }} — POPs, zones, customers</div>
    </div>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Bought this month <i class="fas fa-cart-plus"></i></div>
        <div class="acct-stat-value">{{ $tk($s['month_purchase']) }}</div>
        <div class="acct-stat-foot">{{ \App\Support\Ui::month(now()) }} {{ now()->year }}</div>
    </div>
    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">Sold this month <i class="fas fa-hand-holding-usd"></i></div>
        <div class="acct-stat-value">{{ $tk($s['month_sales']) }}</div>
        <div class="acct-stat-foot">Margin {{ $tk($profit) }}</div>
    </div>
    <div class="acct-stat" style="--accent:{{ $s['low']->isNotEmpty() ? '#dc2626' : '#64748b' }}">
        <div class="acct-stat-label">Low stock <i class="fas fa-exclamation-triangle"></i></div>
        <div class="acct-stat-value">{{ $s['low']->count() }}</div>
        <div class="acct-stat-foot">Damaged / lost this year {{ $tk($s['year_damage']) }}</div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-1 text-primary"></i> Bought, used and sold</h3>
                <span class="small text-muted">Last 6 months</span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 280px;"><canvas id="inv-trend"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tags mr-1 text-primary"></i> Assets by category</h3>
            </div>
            <div class="card-body">
                @forelse (array_slice($s['by_category'], 0, 8, true) as $cat => $value)
                    @continue($value <= 0)
                    <div class="inv-bar-row">
                        <div class="d-flex justify-content-between small">
                            <span class="font-weight-bold">{{ \App\Support\Ui::t($cat) }}</span>
                            <span class="money">{{ $tk($value) }}</span>
                        </div>
                        <div class="acct-progress"><span style="width: {{ round($value / $catTotal * 100, 1) }}%; background: {{ $palette[$loop->index % count($palette)] }}"></span></div>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-box-open"></i>No stock yet</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-marker-alt mr-1 text-primary"></i> Where the equipment is</h3>
                @can('access-inventory-assets')<a href="{{ route('inventory.assets') }}" class="small">All places</a>@endcan
            </div>
            <div class="card-body p-0">
                @forelse ($s['locations']->take(8) as $place)
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background:#eef2ff; color:#4338ca"><i class="fas fa-map-pin"></i></span>
                        <div class="acct-list-main">
                            <div class="acct-list-title">{{ $place['location'] }}</div>
                            <div class="acct-list-sub">{{ $place['items'] }} {{ \App\Support\Ui::t(Str::plural('product', $place['items'])) }}</div>
                        </div>
                        <div class="acct-list-amount">{{ $tk($place['value']) }}</div>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-map-marked-alt"></i>Nothing used anywhere yet</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-1 text-danger"></i> Low stock</h3>
                @can('access-inventory-stock')<a href="{{ route('inventory.items.index', ['stock' => 'low']) }}" class="small">See all</a>@endcan
            </div>
            <div class="card-body p-0">
                @forelse ($s['low']->take(8) as $row)
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background:#fee2e2; color:#b91c1c"><i class="fas fa-box"></i></span>
                        <div class="acct-list-main">
                            <div class="acct-list-title">{{ $row['item']->fullName() }}</div>
                            <div class="acct-list-sub">Keep at least {{ $qty($row['item']->min_stock) }} {{ $row['item']->unit }}</div>
                        </div>
                        <div class="acct-list-amount inv-low">{{ $qty($row['on_hand']) }} {{ $row['item']->unit }}</div>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-check-circle" style="color:#86efac"></i>Everything is above its minimum</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Latest stock entries</h3>
        @can('access-inventory-stock')<a href="{{ route('inventory.entries.index') }}" class="small">All entries</a>@endcan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table inv-table mb-0">
                <thead><tr><th class="pl-3">Date</th><th>Entry</th><th>Product</th><th class="inv-num">Qty</th><th>Where / who</th><th class="inv-num pr-3">Value</th></tr></thead>
                <tbody>
                    @forelse ($recent as $m)
                        @php [$label, , $icon, $color] = InventoryMovement::TYPES[$m->type]; @endphp
                        <tr>
                            <td class="pl-3 text-nowrap">{{ $m->date->format('d M Y') }}</td>
                            <td><span class="inv-type" style="--c: {{ $color }}"><i class="{{ $icon }}"></i> {{ $m->typeLabel() }}</span></td>
                            <td>{{ $m->item?->fullName() }}</td>
                            <td class="inv-num">{{ $qty($m->quantity) }} {{ $m->item?->unit }}</td>
                            <td class="small">{{ $m->location ?? $m->customer?->name ?? $m->party ?? '—' }}</td>
                            <td class="inv-num pr-3">{{ $tk($m->value()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="acct-empty"><i class="fas fa-box-open"></i>No entries yet — add products, then their opening stock and purchases.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="small text-muted">
    <i class="fas fa-info-circle"></i>
    Company assets = what's in the store (at average cost) + equipment in use at POPs, zones and customers.
    Consumables (connectors, ties, tape) are used up when used, so they're not counted — used so far: {{ $tk($s['used_up_value']) }}.
</p>

@stop

@section('js')
<script>
(function () {
    var tk = function (v) { return '৳' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }); };
    var t = @json($trend);
    new Chart(document.getElementById('inv-trend').getContext('2d'), {
        type: 'bar',
        data: {
            labels: t.map(function (r) { return r.label; }),
            datasets: [
                { label: @json(\App\Support\Ui::t('Bought')), data: t.map(function (r) { return r.purchase; }), backgroundColor: '#22c55e' },
                { label: @json(\App\Support\Ui::t('Used')), data: t.map(function (r) { return r.used; }), backgroundColor: '#818cf8' },
                { label: @json(\App\Support\Ui::t('Sold')), data: t.map(function (r) { return r.sale; }), backgroundColor: '#f59e0b' },
            ],
        },
        options: {
            maintainAspectRatio: false,
            legend: { display: true, position: 'bottom', labels: { boxWidth: 12, fontColor: '#475569' } },
            tooltips: { mode: 'index', intersect: false, callbacks: { label: function (i, d) { return d.datasets[i.datasetIndex].label + ': ' + tk(i.yLabel); } } },
            scales: {
                yAxes: [{ ticks: { beginAtZero: true, fontColor: '#94a3b8', callback: function (v) { return tk(v); } }, gridLines: { color: '#eef2f7', drawBorder: false } }],
                xAxes: [{ barPercentage: 0.75, categoryPercentage: 0.6, ticks: { fontColor: '#64748b' }, gridLines: { display: false } }],
            },
        },
    });
})();
</script>
@stop
