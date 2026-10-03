@extends('layouts.accounts-print')

{{-- Owners' one-page summary: company assets, stock, where equipment is, this month. --}}

@use('App\Support\Dec')
@use('App\Services\InventoryStock')
@php
    $tk = fn ($v) => Dec::lakh(round((float) $v, 2));
@endphp

@section('title', 'Assets & Inventory Summary')
@section('back', route('inventory.summary'))
@section('heading', 'Company Assets & Inventory — ' . now()->format('d F Y'))

@section('styles')
    .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; margin-bottom: 1rem; }
    .kpi { border: 1px solid #cbd5e1; border-radius: 6px; padding: .5rem .7rem; }
    .kpi b { display: block; font-size: 1.15rem; }
    .kpi span { font-size: 12px; color: #475569; }
    .kpi.hero { background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
    .kpi.hero span { color: #cbd5e1; }
    h3 { margin: 1rem 0 .4rem; font-size: 1rem; color: #1e3a8a; }
    td.l, th.l { text-align: left; }
    .two { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@endsection

@section('content')
    <div class="kpis">
        <div class="kpi hero"><span>Company assets (৳)</span><b>{{ $tk($s['company_assets']) }}</b></div>
        <div class="kpi"><span>In the store (৳)</span><b>{{ $tk($s['store_value']) }}</b></div>
        <div class="kpi"><span>Equipment in use (৳)</span><b>{{ $tk($s['in_use_value']) }}</b></div>
        <div class="kpi"><span>Bought in {{ now()->format('F') }} (৳)</span><b>{{ $tk($s['month_purchase']) }}</b></div>
        <div class="kpi"><span>Sold in {{ now()->format('F') }} (৳)</span><b>{{ $tk($s['month_sales']) }}</b></div>
        <div class="kpi"><span>Damaged / lost in {{ now()->year }} (৳)</span><b>{{ $tk($s['year_damage']) }}</b></div>
    </div>

    <div class="two">
        <div>
            <h3>Assets by category</h3>
            <table>
                <thead><tr><th class="l">Category</th><th class="num">Value (৳)</th></tr></thead>
                <tbody>
                    @forelse ($s['by_category'] as $cat => $value)
                        @continue($value <= 0)
                        <tr><td class="l">{{ $cat }}</td><td class="num">{{ $tk($value) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="muted">No stock yet</td></tr>
                    @endforelse
                    <tr class="total"><td class="l">Total</td><td class="num">{{ $tk($s['company_assets']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div>
            <h3>Where the equipment is</h3>
            <table>
                <thead><tr><th class="l">Place</th><th class="num">Products</th><th class="num">Value (৳)</th></tr></thead>
                <tbody>
                    @forelse ($s['locations'] as $place)
                        <tr><td class="l">{{ $place['location'] }}</td><td class="num">{{ $place['items'] }}</td><td class="num">{{ $tk($place['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="muted">Nothing in use yet</td></tr>
                    @endforelse
                    <tr class="total"><td class="l">In use</td><td></td><td class="num">{{ $tk($s['in_use_value']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h3>Bought, used and sold — last 6 months (৳)</h3>
    <table>
        <thead><tr><th class="l">Month</th>@foreach ($trend as $t)<th class="num">{{ $t['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            <tr><td class="l">Bought</td>@foreach ($trend as $t)<td class="num">{{ $tk($t['purchase']) }}</td>@endforeach</tr>
            <tr><td class="l">Used</td>@foreach ($trend as $t)<td class="num">{{ $tk($t['used']) }}</td>@endforeach</tr>
            <tr><td class="l">Sold</td>@foreach ($trend as $t)<td class="num">{{ $tk($t['sale']) }}</td>@endforeach</tr>
        </tbody>
    </table>

    @if ($s['low']->isNotEmpty())
        <h3>Low stock</h3>
        <table>
            <thead><tr><th class="l">Product</th><th class="num">In store</th><th class="num">Minimum</th></tr></thead>
            <tbody>
                @foreach ($s['low'] as $row)
                    <tr><td class="l">{{ $row['item']->fullName() }}</td><td class="num neg">{{ InventoryStock::qty($row['on_hand']) }} {{ $row['item']->unit }}</td><td class="num">{{ InventoryStock::qty($row['item']->min_stock) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="muted" style="font-size:12px; margin-top:1rem">
        Company assets = the store at average cost + equipment in use. Consumables used up so far ({{ $tk($s['used_up_value']) }}) are not counted.
        Printed {{ now()->format('d/m/Y h:i A') }} by {{ auth()->user()->name }}.
    </p>
@endsection
