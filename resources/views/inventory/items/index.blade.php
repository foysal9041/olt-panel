@extends('adminlte::page')

@section('title', 'Products & Stock')

@use('App\Support\Dec')
@use('App\Services\InventoryStock')
@use('App\Models\InventoryItem')
@php
    $tk = fn ($v) => '৳' . preg_replace('/\.00$/', '', Dec::lakh(round((float) $v, 2)));
    $qty = fn ($v) => InventoryStock::qty($v);
    $filtered = request()->hasAny(['q', 'category', 'kind', 'stock']);
@endphp

@section('content_header')
<x-inventory.header title="Products & Stock" icon="fas fa-box" subtitle="Everything the company buys and keeps — how many are in the store and where the rest went">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#cat-modal"><i class="fas fa-tags"></i> Categories</button>
    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#item-modal"><i class="fas fa-plus"></i> New product</button>
</x-inventory.header>
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="acct-stats">
    <div class="acct-stat acct-stat-hero">
        <div class="acct-stat-label">In the store <i class="fas fa-warehouse"></i></div>
        <div class="acct-stat-value">{{ $tk($summary['store_value']) }}</div>
        <div class="acct-stat-foot">{{ $summary['items_in_stock'] }} of {{ $summary['items'] }} products in stock</div>
    </div>
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">Equipment in use <i class="fas fa-network-wired"></i></div>
        <div class="acct-stat-value">{{ $tk($summary['in_use_value']) }}</div>
        <div class="acct-stat-foot">Company assets {{ $tk($summary['company_assets']) }}</div>
    </div>
    <a href="{{ route('inventory.items.index', ['stock' => 'low']) }}" class="acct-stat text-reset" style="--accent:{{ $summary['low']->isNotEmpty() ? '#dc2626' : '#64748b' }}">
        <div class="acct-stat-label">Low stock <i class="fas fa-exclamation-triangle"></i></div>
        <div class="acct-stat-value">{{ $summary['low']->count() }}</div>
        <div class="acct-stat-foot">At or below the minimum you set</div>
    </a>
</div>

<div class="card acct-panel">
    <div class="card-header flex-wrap" style="gap:.5rem">
        <form method="GET" class="form-inline flex-wrap" style="gap:.4rem">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, brand, model">
            <select name="category" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ \App\Support\Ui::t($c->name) }} ({{ $c->items_count }})</option>
                @endforeach
            </select>
            <select name="kind" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">Assets &amp; consumables</option>
                @foreach (InventoryItem::KINDS as $k => $kLabel)
                    <option value="{{ $k }}" @selected(request('kind') === $k)>{{ \App\Support\Ui::t($kLabel) }}</option>
                @endforeach
            </select>
            @if (request('stock') === 'low')<input type="hidden" name="stock" value="low">@endif
            <button class="btn btn-sm btn-light border"><i class="fas fa-search"></i></button>
            @if ($filtered)<a href="{{ route('inventory.items.index') }}" class="btn btn-sm btn-link">Clear</a>@endif
        </form>
        <a href="{{ route('inventory.entries.create', ['type' => 'purchase']) }}" class="btn btn-success btn-sm"><i class="fas fa-cart-plus"></i> Stock entry</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table inv-table mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">Product</th>
                        <th>Category</th>
                        <th class="inv-num">In store</th>
                        <th class="inv-num">Avg cost</th>
                        <th class="inv-num">Store value</th>
                        <th class="inv-num">In use</th>
                        <th class="inv-num">Bought</th>
                        <th class="inv-num">Sold</th>
                        <th class="pr-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as ['item' => $item, 'f' => $f])
                        @php $low = (float) $item->min_stock > 0 && $f->on_hand <= (float) $item->min_stock; @endphp
                        <tr @class(['inv-inactive' => ! $item->is_active])>
                            <td class="pl-3">
                                <a href="{{ route('inventory.items.show', $item) }}" class="inv-name">{{ $item->name }}</a>
                                <div class="inv-sub">
                                    {{ trim($item->brand . ' ' . $item->model) ?: '—' }}
                                    · <span class="{{ $item->isAsset() ? 'text-primary' : 'text-muted' }}">{{ $item->kindLabel() }}</span>
                                    @unless ($item->is_active) · <span class="badge badge-secondary">Inactive</span>@endunless
                                </div>
                            </td>
                            <td class="small">{{ \App\Support\Ui::t($item->category?->name ?? '—') }}</td>
                            <td class="inv-num {{ $low ? 'inv-low' : 'font-weight-bold' }}">
                                {{ $qty($f->on_hand) }} <small>{{ $item->unit }}</small>
                                @if ($low)<div class="small"><i class="fas fa-exclamation-triangle"></i> min {{ $qty($item->min_stock) }}</div>@endif
                            </td>
                            <td class="inv-num">{{ $f->avg_cost ? $tk($f->avg_cost) : '—' }}</td>
                            <td class="inv-num">{{ $tk($f->stock_value) }}</td>
                            <td class="inv-num">{{ $f->deployed_qty ? $qty($f->deployed_qty) : '—' }}</td>
                            <td class="inv-num">{{ $f->purchased_qty ? $qty($f->purchased_qty) : '—' }}</td>
                            <td class="inv-num">{{ $f->sold_qty ? $qty($f->sold_qty) : '—' }}</td>
                            <td class="pr-3 text-right text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('inventory.entries.create', ['type' => 'purchase', 'item' => $item->id]) }}" class="btn btn-light border" title="Purchase"><i class="fas fa-cart-plus text-success"></i></a>
                                    <a href="{{ route('inventory.entries.create', ['type' => 'use', 'item' => $item->id]) }}" class="btn btn-light border" title="Use / install"><i class="fas fa-tools text-primary"></i></a>
                                    <a href="{{ route('inventory.entries.create', ['type' => 'sale', 'item' => $item->id]) }}" class="btn btn-light border" title="Sell"><i class="fas fa-hand-holding-usd text-warning"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">
                            <div class="acct-empty">
                                <i class="fas fa-box-open"></i>
                                @if ($filtered) Nothing matches. @else No products yet — add the first one (e.g. ONU, router, fiber cable, SFP). @endif
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- New product --}}
<div class="modal fade" id="item-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('inventory.items.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-box text-primary"></i> New product</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                @include('inventory.items.partials.fields', ['item' => new InventoryItem(['unit' => 'pcs', 'kind' => 'asset', 'is_active' => true])])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Add product</button>
            </div>
        </form>
    </div>
</div>

{{-- Categories --}}
<div class="modal fade" id="cat-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-tags text-primary"></i> Categories</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('inventory.categories.store') }}" class="d-flex mb-3" style="gap:.5rem">
                    @csrf
                    <input type="text" name="name" class="form-control" placeholder="New category, e.g. Generator" required maxlength="100">
                    <button class="btn btn-primary text-nowrap"><i class="fas fa-plus"></i> Add</button>
                </form>
                <ul class="list-group">
                    @foreach ($categories as $c)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span>{{ \App\Support\Ui::t($c->name) }} <span class="text-muted small">× {{ $c->items_count }}</span></span>
                            @if (! $c->items_count)
                                <form method="POST" action="{{ route('inventory.categories.destroy', $c) }}" class="js-confirm-delete" data-confirm-message="Remove this category?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-link btn-sm text-danger p-0"><i class="fas fa-times"></i></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

@stop

@section('js')
@if ($errors->any() && old('_form') === 'item')
<script>$(function () { $('#item-modal').modal('show'); });</script>
@endif
@stop
