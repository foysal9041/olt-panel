@extends('adminlte::page')

@section('title', 'Customers')

@php
    $tk = fn ($v) => '৳' . number_format((float) $v);
    $mac = $customers->where('customer_type', '!=', 'bandwidth_client');
    $bw = $customers->where('customer_type', 'bandwidth_client');
    $bwMonthly = $bw->where('status', true)->sum(fn ($c) => $c->bandwidthRatesTotal());
    $isAdmin = strtolower(auth()->user()->role) === 'admin';
@endphp

@section('content_header')
<x-accounts.header title="Customers" icon="fas fa-users" subtitle="MAC clients (billed by Zone Settlement) and bandwidth clients">
    <a href="{{ route('accounts.customers.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add Customer</a>
</x-accounts.header>
@stop

@section('css')
<style>
    .cu-bar { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .cu-search { position: relative; flex: 1 1 260px; max-width: 420px; }
    .cu-search i { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .cu-search input { padding-left: 2.2rem; border-radius: 999px; }
    .cu-filter .btn { border-radius: 999px; font-size: .8rem; font-weight: 600; }
    .cu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .cu-card { display: flex; flex-direction: column; border: 1px solid #eef2f7; border-radius: 1rem; background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 6px 18px -10px rgba(15, 23, 42, .14); transition: transform .15s, box-shadow .15s; }
    .cu-card:hover { transform: translateY(-2px); box-shadow: 0 2px 4px rgba(15, 23, 42, .05), 0 14px 28px -12px rgba(15, 23, 42, .2); }
    .cu-card.inactive { opacity: .6; }
    .cu-top { display: flex; align-items: center; gap: .75rem; padding: 1rem 1rem .6rem; }
    .cu-avatar { flex: none; display: grid; place-items: center; width: 2.7rem; height: 2.7rem; border-radius: .8rem; font-weight: 800; font-size: .9rem; text-transform: uppercase; }
    .cu-avatar.mac { background: #e0f2fe; color: #0369a1; }
    .cu-avatar.bw { background: #ede9fe; color: #6d28d9; }
    .cu-name { font-weight: 700; color: #0f172a; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cu-name:hover { color: #4f46e5; text-decoration: none; }
    .cu-user { font-size: .78rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cu-dot { flex: none; width: .55rem; height: .55rem; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px #dcfce7; }
    .cu-card.inactive .cu-dot { background: #94a3b8; box-shadow: 0 0 0 3px #f1f5f9; }
    .cu-info { padding: 0 1rem .75rem; font-size: .83rem; color: #475569; }
    .cu-info div { display: flex; align-items: center; gap: .5rem; margin-top: .2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cu-info i { width: .9rem; text-align: center; color: #94a3b8; }
    .cu-money { margin: 0 1rem .8rem; padding: .55rem .7rem; border-radius: .7rem; background: #f8fafc; display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; }
    .cu-money span { font-size: .72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cu-money b { font-variant-numeric: tabular-nums; color: #0f172a; white-space: nowrap; }
    .cu-actions { margin-top: auto; display: flex; border-top: 1px solid #f1f5f9; }
    .cu-actions > * { flex: 1; }
    .cu-actions a, .cu-actions button { display: block; width: 100%; padding: .5rem; border: 0; background: none; font-size: .8rem; font-weight: 600; color: #475569; text-align: center; }
    .cu-actions > * + * { border-left: 1px solid #f1f5f9; }
    .cu-actions a:hover, .cu-actions button:hover { background: #f8fafc; color: #4f46e5; text-decoration: none; }
    .cu-actions button.text-danger:hover { color: #dc2626 !important; }
    .cu-none { grid-column: 1 / -1; }
    .bw-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .4rem .3rem .7rem; margin: 0 .3rem .4rem 0; border-radius: 999px; background: #ede9fe; color: #5b21b6; font-size: .82rem; font-weight: 600; }
    .bw-chip button { border: 0; background: rgba(255, 255, 255, .7); color: #6d28d9; width: 1.2rem; height: 1.2rem; border-radius: 50%; line-height: 1; font-size: .8rem; }
</style>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">Customers <i class="fas fa-users"></i></div>
        <div class="acct-stat-value">{{ $customers->count() }}</div>
        <div class="acct-stat-foot">{{ $customers->where('status', true)->count() }} active</div>
    </div>
    <div class="acct-stat" style="--accent:#0284c7">
        <div class="acct-stat-label">MAC clients <i class="fas fa-network-wired"></i></div>
        <div class="acct-stat-value">{{ $mac->count() }}</div>
        <div class="acct-stat-foot">Billed by Zone Settlement</div>
    </div>
    <div class="acct-stat" style="--accent:#7c3aed">
        <div class="acct-stat-label">Bandwidth clients <i class="fas fa-tachometer-alt"></i></div>
        <div class="acct-stat-value">{{ $bw->count() }}</div>
        <div class="acct-stat-foot">{{ $tk($bwMonthly) }} / month at set rates</div>
    </div>
</div>

<div class="cu-bar">
    <div class="cu-search">
        <i class="fas fa-search"></i>
        <input type="search" id="cu-q" class="form-control" placeholder="Search name, username, zone or phone…" autocomplete="off">
    </div>
    <div class="btn-group cu-filter" role="group" aria-label="Type">
        <button type="button" class="btn btn-sm btn-primary" data-type="">All {{ $customers->count() }}</button>
        <button type="button" class="btn btn-sm btn-light border" data-type="mac">MAC {{ $mac->count() }}</button>
        <button type="button" class="btn btn-sm btn-light border" data-type="bw">Bandwidth {{ $bw->count() }}</button>
    </div>
</div>

<div class="cu-grid" id="cu-grid">
    @forelse ($customers as $customer)
        @php
            $isBw = $customer->isBandwidthClient();
            $last = $customer->settlementRows->firstWhere('included', true);
            $lastCalc = $last?->calc($last->settlement?->bkash_percent);
        @endphp
        <div @class(['cu-card', 'inactive' => ! $customer->status]) data-type="{{ $isBw ? 'bw' : 'mac' }}"
             data-q="{{ Str::lower($customer->name . ' ' . $customer->username . ' ' . $customer->zone . ' ' . $customer->phone) }}">
            <div class="cu-top">
                <span class="cu-avatar {{ $isBw ? 'bw' : 'mac' }}">{{ mb_substr($customer->name, 0, 2) }}</span>
                <div style="min-width:0; flex:1">
                    <a href="{{ route('accounts.customers.show', $customer) }}" class="cu-name d-block">{{ $customer->name }}</a>
                    <div class="cu-user">{{ $customer->username ?: '—' }} · {{ $customer->customerTypeLabel() }}</div>
                </div>
                <span class="cu-dot" title="{{ $customer->status ? 'Active' : 'Inactive' }}"></span>
            </div>
            <div class="cu-info">
                <div><i class="fas fa-map-marker-alt"></i> {{ $customer->zone ?: 'No zone' }}</div>
                <div><i class="fas fa-phone"></i> {{ $customer->phone ?: '—' }}</div>
            </div>
            @if ($isBw)
                @php $bal = (float) $customer->bandwidthBalance(); @endphp
                <div class="cu-money"><span>Monthly · {{ $customer->bandwidthRates->count() }} {{ Str::plural('type', $customer->bandwidthRates->count()) }}</span><b>{{ $tk($customer->bandwidthRatesTotal()) }}</b></div>
                <div class="cu-money" style="margin-top:-.4rem"><span>{{ $bal > 0 ? 'Due now' : ($bal < 0 ? 'Advance' : 'Settled') }}</span><b class="{{ $bal > 0 ? 'text-danger' : 'text-income' }}">{{ $tk(abs($bal)) }}</b></div>
            @elseif ($last)
                <div class="cu-money"><span>Net Bill · {{ $last->settlement?->month?->format('M Y') }}</span><b class="text-income">{{ $tk($lastCalc['income']) }}</b></div>
            @else
                <div class="cu-money"><span>No settlement yet</span><b class="text-muted">—</b></div>
            @endif
            <div class="cu-actions">
                <a href="{{ route('accounts.customers.show', $customer) }}"><i class="fas fa-eye"></i> View</a>
                <a href="{{ route('accounts.customers.edit', $customer) }}"><i class="fas fa-pen"></i> Edit</a>
                @if ($isAdmin)
                    <form action="{{ route('accounts.customers.destroy', $customer) }}" method="POST" class="js-confirm-delete"
                          data-confirm-message="Delete customer {{ $customer->name }}?">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-danger"><i class="fas fa-trash-alt"></i></button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="cu-none card acct-panel"><div class="acct-empty"><i class="fas fa-users"></i>No customers yet.</div></div>
    @endforelse
    <div class="cu-none card acct-panel d-none" id="cu-empty"><div class="acct-empty"><i class="fas fa-search"></i>No customer matches.</div></div>
</div>

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-tachometer-alt mr-1" style="color:#7c3aed"></i> Bandwidth types</h3>
        <span class="small text-muted">"fixed" = a set monthly amount (VAS, Billing); click to switch</span>
    </div>
    <div class="card-body">
        <div class="mb-2">
            @forelse ($bandwidthTypes as $type)
                <span class="bw-chip">
                    {{ $type->name }}
                    <form action="{{ route('accounts.bandwidth-types.update', $type) }}" method="POST" class="d-inline">
                        @csrf @method('PUT')
                        <input type="hidden" name="flat" value="{{ $type->flat ? 0 : 1 }}">
                        <button type="submit" title="Click to switch" style="width:auto; padding:0 .45rem; border-radius:999px; font-size:.7rem">{{ $type->flat ? 'fixed' : 'per Mbps' }}</button>
                    </form>
                    <form action="{{ route('accounts.bandwidth-types.destroy', $type->id) }}" method="POST" class="d-inline js-confirm-delete"
                          data-confirm-message="Remove bandwidth type {{ $type->name }}?">
                        @csrf @method('DELETE')
                        <button type="submit" title="Remove">&times;</button>
                    </form>
                </span>
            @empty
                <span class="text-muted small">No bandwidth types yet.</span>
            @endforelse
        </div>
        <form method="POST" action="{{ route('accounts.bandwidth-types.store') }}" class="d-flex" style="gap:.5rem; max-width: 420px">
            @csrf
            <input type="text" name="name" class="form-control form-control-sm" placeholder="New type, e.g. VAS" required>
            <label class="small text-nowrap mb-0 d-flex align-items-center" style="gap:.3rem"><input type="checkbox" name="flat" value="1"> fixed amount</label>
            <button type="submit" class="btn btn-sm text-nowrap" style="background:#7c3aed; color:#fff"><i class="fas fa-plus"></i> Add</button>
        </form>
    </div>
</div>

@stop

@section('js')
<script>
(function () {
    var q = document.getElementById('cu-q'), type = '', cards = document.querySelectorAll('#cu-grid .cu-card'), empty = document.getElementById('cu-empty');

    function apply() {
        var term = q.value.trim().toLowerCase(), shown = 0;
        cards.forEach(function (c) {
            var ok = (!type || c.dataset.type === type) && (!term || c.dataset.q.indexOf(term) !== -1);
            c.classList.toggle('d-none', !ok);
            if (ok) shown++;
        });
        empty.classList.toggle('d-none', shown > 0 || cards.length === 0);
    }

    q.addEventListener('input', apply);
    document.querySelectorAll('.cu-filter [data-type]').forEach(function (b) {
        b.addEventListener('click', function () {
            type = b.dataset.type;
            document.querySelectorAll('.cu-filter [data-type]').forEach(function (x) {
                x.classList.toggle('btn-primary', x === b);
                x.classList.toggle('btn-light', x !== b);
                x.classList.toggle('border', x !== b);
            });
            apply();
        });
    });
})();
</script>
@stop
