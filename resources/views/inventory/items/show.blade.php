@extends('adminlte::page')

@section('title', $item->name)

@use('App\Support\Dec')
@use('App\Services\InventoryStock')
@use('App\Models\InventoryMovement')
@php
    $tk = fn ($v) => '৳' . preg_replace('/\.00$/', '', Dec::lakh(round((float) $v, 2)));
    $qty = fn ($v) => InventoryStock::qty($v);
    $low = (float) $item->min_stock > 0 && $f->on_hand <= (float) $item->min_stock;
    $user = auth()->user();
@endphp

@section('content_header')
<x-inventory.header :title="$item->name" icon="fas fa-box" :back="route('inventory.items.index')"
    :subtitle="trim(implode(' · ', array_filter([trim($item->brand . ' ' . $item->model), \App\Support\Ui::t($item->category?->name), \App\Support\Ui::t($item->kindLabel())])))">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#item-modal"><i class="fas fa-pen"></i> Edit</button>
</x-inventory.header>
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="inv-types">
    @foreach (InventoryMovement::TYPES as $t => [$tLabel, $dir, $tIcon, $tColor])
        <a href="{{ route('inventory.entries.create', ['type' => $t, 'item' => $item->id]) }}" style="--c: {{ $tColor }}">
            <i class="{{ $tIcon }}"></i> <span>{{ \App\Support\Ui::t($tLabel) }}</span>
        </a>
    @endforeach
</div>

<div class="acct-stats">
    <div class="acct-stat acct-stat-hero {{ $low ? 'neg' : '' }}">
        <div class="acct-stat-label">In the store <i class="fas fa-warehouse"></i></div>
        <div class="acct-stat-value">{{ $qty($f->on_hand) }} <small>{{ $item->unit }}</small></div>
        <div class="acct-stat-foot">
            @if ($low) <i class="fas fa-exclamation-triangle"></i> At or below the minimum ({{ $qty($item->min_stock) }})
            @else Worth {{ $tk($f->stock_value) }} @endif
        </div>
    </div>
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">In use <i class="fas fa-network-wired"></i></div>
        <div class="acct-stat-value">{{ $qty($f->deployed_qty) }} <small>{{ $item->unit }}</small></div>
        <div class="acct-stat-foot">At {{ $locations->count() }} {{ \App\Support\Ui::t(Str::plural('place', $locations->count())) }} · {{ $tk($f->deployed_value) }}</div>
    </div>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Average cost <i class="fas fa-calculator"></i></div>
        <div class="acct-stat-value">{{ $tk($f->avg_cost) }}</div>
        <div class="acct-stat-foot">Bought {{ $qty($f->purchased_qty) }} {{ $item->unit }} for {{ $tk($f->purchased_value) }}</div>
    </div>
    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">Sold <i class="fas fa-hand-holding-usd"></i></div>
        <div class="acct-stat-value">{{ $qty($f->sold_qty) }} <small>{{ $item->unit }}</small></div>
        <div class="acct-stat-foot">For {{ $tk($f->sold_value) }} · margin {{ $tk($f->sold_value - $f->sold_cost) }}</div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-map-marker-alt mr-1 text-primary"></i> Where it is now</h3></div>
            <div class="card-body p-0">
                <div class="acct-list-row">
                    <span class="acct-avatar" style="background:#e0f2fe; color:#0369a1"><i class="fas fa-warehouse"></i></span>
                    <div class="acct-list-main"><div class="acct-list-title">Store</div></div>
                    <div class="acct-list-amount">{{ $qty($f->on_hand) }} {{ $item->unit }}</div>
                </div>
                @foreach ($locations as $loc)
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background:#eef2ff; color:#4338ca"><i class="fas fa-map-pin"></i></span>
                        <div class="acct-list-main">
                            <div class="acct-list-title">{{ $loc->location }}</div>
                            <div class="acct-list-sub">Since {{ \Illuminate\Support\Carbon::parse($loc->last_date)->format('d M Y') }} · {{ $tk($loc->value) }}</div>
                        </div>
                        <div class="acct-list-amount">{{ $qty($loc->qty) }} {{ $item->unit }}</div>
                    </div>
                @endforeach
                @if ($f->sold_qty > 0)
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background:#fef3c7; color:#b45309"><i class="fas fa-hand-holding-usd"></i></span>
                        <div class="acct-list-main"><div class="acct-list-title">Sold</div></div>
                        <div class="acct-list-amount">{{ $qty($f->sold_qty) }} {{ $item->unit }}</div>
                    </div>
                @endif
                @if ($f->damaged_qty > 0)
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background:#fee2e2; color:#b91c1c"><i class="fas fa-heart-broken"></i></span>
                        <div class="acct-list-main"><div class="acct-list-title">Damaged / lost</div></div>
                        <div class="acct-list-amount">{{ $qty($f->damaged_qty) }} {{ $item->unit }}</div>
                    </div>
                @endif
            </div>
        </div>
        @if ($item->notes)
            <div class="card acct-panel">
                <div class="card-body small" style="white-space: pre-line">{{ $item->notes }}</div>
            </div>
        @endif
    </div>

    <div class="col-lg-8">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-stream mr-1 text-primary"></i> Stock history</h3><span class="small text-muted">{{ $movements->count() }} {{ \App\Support\Ui::t(Str::plural('entry', $movements->count())) }}</span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table inv-table mb-0">
                        <thead><tr><th class="pl-3">Date</th><th>Entry</th><th class="inv-num">Qty</th><th>Where / who</th><th class="inv-num">Value</th><th class="inv-num">Store after</th><th class="pr-3"></th></tr></thead>
                        <tbody>
                            @forelse ($movements as $m)
                                @php [, , $mIcon, $mColor] = InventoryMovement::TYPES[$m->type]; $out = ! $m->isIn() && ! ($m->type === 'damage' && $m->location); @endphp
                                <tr>
                                    <td class="pl-3 text-nowrap">{{ $m->date->format('d M Y') }}</td>
                                    <td>
                                        <span class="inv-type" style="--c: {{ $mColor }}"><i class="{{ $mIcon }}"></i> {{ $m->typeLabel() }}</span>
                                        @if ($m->reference)<div class="inv-sub">#{{ $m->reference }}</div>@endif
                                        @if ($m->transaction_id)<div class="inv-sub"><i class="fas fa-link"></i> In Income &amp; Expenses</div>@endif
                                    </td>
                                    <td class="inv-num font-weight-bold {{ $m->isIn() ? 'text-success' : 'text-danger' }}">{{ $m->isIn() ? '+' : '−' }}{{ $qty($m->quantity) }}</td>
                                    <td class="small">
                                        {{ $m->location ?? $m->customer?->name ?? $m->party ?? '—' }}
                                        @if ($m->serials)<div class="inv-sub text-truncate" style="max-width: 14rem" title="{{ $m->serials }}"><i class="fas fa-barcode"></i> {{ $m->serials }}</div>@endif
                                        @if ($m->note)<div class="inv-sub">{{ $m->note }}</div>@endif
                                    </td>
                                    <td class="inv-num">{{ $tk($m->value()) }}</td>
                                    <td class="inv-num">{{ $qty($m->store_balance) }}</td>
                                    <td class="pr-3 text-right">
                                        @if ($user->isAdmin() || ($m->recorded_by === $user->id && $m->created_at?->isToday()))
                                            <form method="POST" action="{{ route('inventory.entries.destroy', $m) }}" class="js-confirm-delete" data-confirm-message="{{ \App\Support\Ui::t('Remove this entry?') }}{{ $m->transaction_id ? ' ' . \App\Support\Ui::t('Its Income & Expenses entry is removed too.') : '' }}">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-link btn-sm text-danger p-0" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="acct-empty"><i class="fas fa-box-open"></i>No entries yet — start with its opening stock or a purchase.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit --}}
<div class="modal fade" id="item-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('inventory.items.update', $item) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen text-primary"></i> Edit product</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                @include('inventory.items.partials.fields', ['item' => $item])
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    @if ($movements->isEmpty())
                        <button type="submit" form="item-delete" class="btn btn-link text-danger p-0"><i class="fas fa-trash"></i> Remove product</button>
                    @endif
                </div>
                <div>
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if ($movements->isEmpty())
    <form id="item-delete" method="POST" action="{{ route('inventory.items.destroy', $item) }}" class="js-confirm-delete" data-confirm-message="Remove this product?">
        @csrf @method('DELETE')
    </form>
@endif

@stop

@section('js')
@if ($errors->any() && old('_form') === 'item')
<script>$(function () { $('#item-modal').modal('show'); });</script>
@endif
@stop
