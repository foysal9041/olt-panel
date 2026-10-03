@extends('adminlte::page')

@section('title', 'Company Assets')

@use('App\Support\Dec')
@use('App\Services\InventoryStock')
@php
    $tk = fn ($v) => '৳' . preg_replace('/\.00$/', '', Dec::lakh(round((float) $v, 2)));
    $qty = fn ($v) => InventoryStock::qty($v);
    $storeValue = $store->sum(fn ($r) => $r['f']->stock_value);
    $placesValue = $places->sum('value');
@endphp

@section('content_header')
<x-inventory.header title="Company Assets" icon="fas fa-building" subtitle="Where everything the company owns is — the store and every POP, zone and customer">
    @can('access-inventory-summary')
        <a href="{{ route('inventory.summary', ['print' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print summary</a>
    @endcan
</x-inventory.header>
@stop

@section('content')

<div class="acct-stats">
    <div class="acct-stat acct-stat-hero">
        <div class="acct-stat-label">Company assets <i class="fas fa-building"></i></div>
        <div class="acct-stat-value">{{ $tk($s['company_assets']) }}</div>
        <div class="acct-stat-foot">Store + equipment in use</div>
    </div>
    <div class="acct-stat" style="--accent:#0284c7">
        <div class="acct-stat-label">In the store <i class="fas fa-warehouse"></i></div>
        <div class="acct-stat-value">{{ $tk($s['store_value']) }}</div>
        <div class="acct-stat-foot">{{ $s['items_in_stock'] }} {{ \App\Support\Ui::t(Str::plural('product', $s['items_in_stock'])) }} in stock</div>
    </div>
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">In use <i class="fas fa-network-wired"></i></div>
        <div class="acct-stat-value">{{ $tk($s['in_use_value']) }}</div>
        <div class="acct-stat-foot">At {{ $s['locations']->count() }} {{ \App\Support\Ui::t(Str::plural('place', $s['locations']->count())) }}</div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:.5rem">
    <div class="wk-tabs mb-0">
        @foreach (['asset' => 'Company assets', 'consumable' => 'Consumables', 'all' => 'Everything'] as $k => $kLabel)
            <a href="{{ route('inventory.assets', array_filter(['kind' => $k, 'q' => request('q')])) }}" @class(['active' => $kind === $k])>{{ \App\Support\Ui::t($kLabel) }}</a>
        @endforeach
    </div>
    <form method="GET" class="form-inline" style="gap:.4rem">
        <input type="hidden" name="kind" value="{{ $kind }}">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Find a place or product">
        <button class="btn btn-sm btn-light border"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="row">
    {{-- The store --}}
    @if ($store->isNotEmpty() && ! request('q'))
        <div class="col-lg-6 col-xl-4 mb-4">
            <div class="card acct-panel inv-place mb-0">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-warehouse mr-1 text-info"></i> Store</h3>
                    <span class="inv-place-value">{{ $tk($storeValue) }}</span>
                </div>
                <div class="card-body p-0">
                    @foreach ($store->take(12) as ['item' => $item, 'f' => $f])
                        <div class="acct-list-row py-2">
                            <div class="acct-list-main">
                                @can('access-inventory-stock')
                                    <a href="{{ route('inventory.items.show', $item) }}" class="acct-list-title d-block">{{ $item->fullName() }}</a>
                                @else
                                    <div class="acct-list-title">{{ $item->fullName() }}</div>
                                @endcan
                                <div class="acct-list-sub">{{ $tk($f->stock_value) }}</div>
                            </div>
                            <div class="acct-list-amount">{{ $qty($f->on_hand) }} <small>{{ $item->unit }}</small></div>
                        </div>
                    @endforeach
                    @if ($store->count() > 12)
                        <div class="acct-list-row py-2 small text-muted">+ {{ $store->count() - 12 }} more</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Every place things are used --}}
    @forelse ($places as $place)
        <div class="col-lg-6 col-xl-4 mb-4">
            <div class="card acct-panel inv-place mb-0">
                <div class="card-header">
                    <h3 class="card-title text-truncate" title="{{ $place['location'] }}"><i class="fas fa-map-pin mr-1 text-primary"></i> {{ $place['location'] }}</h3>
                    <span class="inv-place-value">{{ $tk($place['value']) }}</span>
                </div>
                <div class="card-body p-0">
                    @foreach ($place['rows'] as $r)
                        <div class="acct-list-row py-2">
                            <div class="acct-list-main">
                                @can('access-inventory-stock')
                                    <a href="{{ route('inventory.items.show', $r->item) }}" class="acct-list-title d-block">{{ $r->item->fullName() }}</a>
                                @else
                                    <div class="acct-list-title">{{ $r->item->fullName() }}</div>
                                @endcan
                                <div class="acct-list-sub">Since {{ \Illuminate\Support\Carbon::parse($r->last_date)->format('d M Y') }} · {{ $tk($r->value) }}</div>
                            </div>
                            <div class="acct-list-amount">{{ $qty($r->qty) }} <small>{{ $r->item->unit }}</small></div>
                        </div>
                    @endforeach
                </div>
                @can('access-inventory-stock')
                    <div class="card-footer bg-white small">
                        <a href="{{ route('inventory.entries.index', ['location' => $place['location']]) }}"><i class="fas fa-history"></i> History of this place</a>
                    </div>
                @endcan
            </div>
        </div>
    @empty
        @if ($store->isEmpty() || request('q'))
            <div class="col-12">
                <div class="card acct-panel"><div class="acct-empty"><i class="fas fa-map-marked-alt"></i>
                    @if (request('q')) Nothing matches. @else Nothing recorded yet — add products and their stock, then record where they are used. @endif
                </div></div>
            </div>
        @endif
    @endforelse
</div>

<p class="small text-muted">
    <i class="fas fa-info-circle"></i>
    Values are what the items cost. Something used at a place stays there until it's returned to the store, sold or marked damaged.
</p>

@stop
