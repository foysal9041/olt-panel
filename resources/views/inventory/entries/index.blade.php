@extends('adminlte::page')

@section('title', 'Stock Entries')

@use('App\Support\Dec')
@use('App\Services\InventoryStock')
@use('App\Models\InventoryMovement')
@php
    $tk = fn ($v) => '৳' . preg_replace('/\.00$/', '', Dec::lakh(round((float) $v, 2)));
    $qty = fn ($v) => InventoryStock::qty($v);
    $user = auth()->user();
    $filtered = array_filter($filters);
@endphp

@section('content_header')
<x-inventory.header title="Stock Entries" icon="fas fa-exchange-alt" subtitle="Every purchase, use, return, sale and count — newest first">
    <a href="{{ route('inventory.entries.create', ['type' => 'purchase']) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New entry</a>
</x-inventory.header>
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="inv-types">
    @foreach (InventoryMovement::TYPES as $t => [$tLabel, , $tIcon, $tColor])
        @php $sum = $totals->get($t); @endphp
        <a href="{{ route('inventory.entries.index', array_merge($filters, ['type' => ($filters['type'] ?? null) === $t ? null : $t])) }}" @class(['active' => ($filters['type'] ?? null) === $t]) style="--c: {{ $tColor }}">
            <i class="{{ $tIcon }}"></i>
            <span>{{ \App\Support\Ui::t($tLabel) }}<br><small class="text-muted">{{ $sum ? $sum->n . ' · ' . $tk($sum->value) : '—' }}</small></span>
        </a>
    @endforeach
</div>

<div class="card acct-panel">
    <div class="card-header flex-wrap" style="gap:.5rem">
        <form method="GET" class="form-inline flex-wrap" style="gap:.4rem">
            @if (! empty($filters['type']))<input type="hidden" name="type" value="{{ $filters['type'] }}">@endif
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search product, place, supplier, serial">
            <select name="item" class="form-control form-control-sm" style="max-width: 14rem">
                <option value="">All products</option>
                @foreach ($items as $i)
                    <option value="{{ $i->id }}" @selected(($filters['item'] ?? '') == $i->id)>{{ $i->fullName() }}</option>
                @endforeach
            </select>
            <select name="location" class="form-control form-control-sm" style="max-width: 12rem">
                <option value="">Any place</option>
                @foreach ($locations as $l)
                    <option value="{{ $l }}" @selected(($filters['location'] ?? '') === $l)>{{ $l }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" title="From">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" title="To">
            <button class="btn btn-sm btn-light border"><i class="fas fa-filter"></i> Filter</button>
            @if ($filtered)<a href="{{ route('inventory.entries.index') }}" class="btn btn-sm btn-link">Clear</a>@endif
        </form>
        <span class="small text-muted">{{ $entries->total() }} {{ \App\Support\Ui::t(Str::plural('entry', $entries->total())) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table inv-table mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">Date</th>
                        <th>Entry</th>
                        <th>Product</th>
                        <th class="inv-num">Qty</th>
                        <th class="inv-num">Rate</th>
                        <th class="inv-num">Value</th>
                        <th>Where / who</th>
                        <th>By</th>
                        <th class="pr-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $m)
                        @php [, , $mIcon, $mColor] = InventoryMovement::TYPES[$m->type] ?? ['', '', 'fas fa-circle', '#64748b']; @endphp
                        <tr>
                            <td class="pl-3 text-nowrap">{{ $m->date->format('d M Y') }}</td>
                            <td>
                                <span class="inv-type" style="--c: {{ $mColor }}"><i class="{{ $mIcon }}"></i> {{ $m->typeLabel() }}</span>
                                @if ($m->reference)<div class="inv-sub">#{{ $m->reference }}</div>@endif
                            </td>
                            <td>
                                @if ($m->item)<a href="{{ route('inventory.items.show', $m->item) }}" class="inv-name">{{ $m->item->name }}</a>
                                <div class="inv-sub">{{ trim($m->item->brand . ' ' . $m->item->model) }}</div>@endif
                            </td>
                            <td class="inv-num font-weight-bold {{ $m->isIn() ? 'text-success' : 'text-danger' }}">{{ $m->isIn() ? '+' : '−' }}{{ $qty($m->quantity) }} <small>{{ $m->item?->unit }}</small></td>
                            <td class="inv-num">{{ $tk($m->type === 'sale' ? $m->unit_price : $m->unit_cost) }}</td>
                            <td class="inv-num">{{ $tk($m->value()) }}</td>
                            <td class="small">
                                {{ $m->location ?? $m->customer?->name ?? $m->party ?? '—' }}
                                @if ($m->location && $m->customer)<div class="inv-sub">{{ $m->customer->name }}</div>@endif
                                @if ($m->serials)<div class="inv-sub text-truncate" style="max-width: 13rem" title="{{ $m->serials }}"><i class="fas fa-barcode"></i> {{ $m->serials }}</div>@endif
                                @if ($m->note)<div class="inv-sub">{{ $m->note }}</div>@endif
                                @if ($m->transaction_id)<div class="inv-sub text-primary"><i class="fas fa-link"></i> In Income &amp; Expenses</div>@endif
                            </td>
                            <td class="small text-nowrap">{{ $m->recordedBy?->name ?? '—' }}</td>
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
                        <tr><td colspan="9"><div class="acct-empty"><i class="fas fa-exchange-alt"></i>@if ($filtered) Nothing matches. @else No stock entries yet. @endif</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($entries->hasPages())
        <div class="card-footer">{{ $entries->links() }}</div>
    @endif
</div>

@stop
