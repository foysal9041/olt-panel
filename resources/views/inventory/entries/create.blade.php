@extends('adminlte::page')

@section('title', 'Stock Entry')

@use('App\Services\InventoryStock')
@use('App\Models\InventoryMovement')
@use('App\Models\Transaction')
@php
    [$typeLabel, $dir, $typeIcon, $typeColor] = InventoryMovement::TYPES[$type];
    $costed = in_array($type, InventoryMovement::COSTED, true);
    $needsPlace = in_array($type, ['use', 'return', 'damage'], true);
    $blank = (object) ['on_hand' => 0, 'avg_cost' => 0, 'deployed_qty' => 0];
    // For the item picker: what's in the store and where each item is used.
    $itemData = $items->mapWithKeys(fn ($i) => [$i->id => [
        'unit' => $i->unit,
        'stock' => ($figures->get($i->id) ?? $blank)->on_hand,
        'avg' => ($figures->get($i->id) ?? $blank)->avg_cost,
        'price' => $i->sale_price,
        'places' => $deployed->where('inventory_item_id', $i->id)->map(fn ($r) => ['location' => $r->location, 'qty' => $r->qty])->values(),
    ]]);
    $incomeHeads = $heads->where('type', 'income')->filter(fn ($h) => $h->plGroup() !== 'none');
    $expenseHeads = $heads->where('type', 'expense');
    $defaultExpense = $expenseHeads->first(fn ($h) => $h->name === 'ইন্টারনেট মালামাল')?->id;
    $defaultIncome = $incomeHeads->first(fn ($h) => str_contains($h->name, 'Product'))?->id;
    $back = url()->previous() !== url()->current() && str_contains(url()->previous(), '/inventory/items/') ? url()->previous() : null;
@endphp

@section('content_header')
<x-inventory.header title="Stock Entry" icon="fas fa-exchange-alt" subtitle="Purchase, use, return, sale, damage or a stock count — one product per entry" />
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="inv-types">
    @foreach (InventoryMovement::TYPES as $t => [$tLabel, , $tIcon, $tColor])
        <a href="{{ route('inventory.entries.create', array_filter(['type' => $t, 'item' => $selected ?? old('inventory_item_id')])) }}" @class(['active' => $t === $type]) style="--c: {{ $tColor }}">
            <i class="{{ $tIcon }}"></i> <span>{{ \App\Support\Ui::t($tLabel) }}</span>
        </a>
    @endforeach
</div>

<form method="POST" action="{{ route('inventory.entries.store') }}" id="entry-form">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    @if ($back)<input type="hidden" name="back" value="{{ $back }}">@endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title" style="color: {{ $typeColor }}"><i class="{{ $typeIcon }} mr-1"></i> {{ \App\Support\Ui::t($typeLabel) }}</h3>
                    <span class="small text-muted">
                        @switch($type)
                            @case('opening') What was already in the store before you started here. @break
                            @case('purchase') Bought — comes into the store. @break
                            @case('use') Taken from the store and used / installed somewhere. @break
                            @case('return') Brought back to the store from where it was used. @break
                            @case('sale') Sold — leaves the store. @break
                            @case('damage') Broken or lost — from the store or from where it was used. @break
                            @case('adjust_in') The count found more than the system shows. @break
                            @case('adjust_out') The count found less than the system shows. @break
                        @endswitch
                    </span>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-md-8 form-group">
                            <label>Product <span class="text-danger">*</span></label>
                            <select name="inventory_item_id" id="inv-item" class="form-control" required>
                                <option value="">Choose a product…</option>
                                @foreach ($items->groupBy(fn ($i) => $i->category?->name ?? 'Other') as $cat => $group)
                                    <optgroup label="{{ \App\Support\Ui::t($cat) }}">
                                        @foreach ($group as $i)
                                            <option value="{{ $i->id }}" @selected((int) old('inventory_item_id', $selected) === $i->id)>{{ $i->fullName() }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="form-text text-muted" id="inv-stock-hint">&nbsp;</small>
                            @if ($items->isEmpty())
                                <small class="form-text text-danger">No products yet — <a href="{{ route('inventory.items.index') }}">add one first</a>.</small>
                            @endif
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" max="{{ now()->addDay()->toDateString() }}" class="form-control" required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Quantity <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="quantity" id="inv-qty" value="{{ old('quantity') }}" step="0.01" min="0.01" class="form-control" required>
                                <div class="input-group-append"><span class="input-group-text" id="inv-unit">pcs</span></div>
                            </div>
                        </div>

                        @if ($costed)
                            <div class="col-md-4 form-group">
                                <label>Cost of one unit (৳) <span class="text-danger">*</span></label>
                                <input type="number" name="unit_cost" id="inv-cost" value="{{ old('unit_cost') }}" step="0.01" min="0" class="form-control" required>
                            </div>
                        @endif
                        @if ($type === 'sale')
                            <div class="col-md-4 form-group">
                                <label>Sale price of one unit (৳) <span class="text-danger">*</span></label>
                                <input type="number" name="unit_price" id="inv-price" value="{{ old('unit_price') }}" step="0.01" min="0" class="form-control" required>
                            </div>
                        @endif
                        @if ($costed || $type === 'sale')
                            <div class="col-md-4 form-group">
                                <label>Total</label>
                                <div class="form-control bg-light font-weight-bold" id="inv-total">৳0</div>
                            </div>
                        @endif

                        @if ($needsPlace)
                            <div class="col-md-8 form-group">
                                <label>
                                    @if ($type === 'use') Where is it used? <span class="text-danger">*</span>
                                    @elseif ($type === 'return') Coming back from <span class="text-danger">*</span>
                                    @else Where was it? <small class="text-muted">(empty = from the store)</small>
                                    @endif
                                </label>
                                <input type="text" name="location" id="inv-location" value="{{ old('location') }}" class="form-control" list="inv-locations" maxlength="255"
                                       placeholder="Zone, POP or customer — e.g. Navaron POP" @required(in_array($type, ['use', 'return'], true))>
                                <datalist id="inv-locations">@foreach ($locations as $l)<option value="{{ $l }}">@endforeach</datalist>
                                <div id="inv-places" class="mt-1"></div>
                            </div>
                        @endif

                        @if (in_array($type, ['purchase', 'sale', 'opening'], true) || $type === 'use')
                            <div class="col-md-4 form-group">
                                <label>
                                    @if ($type === 'sale') Customer @elseif ($type === 'use') For customer <small class="text-muted">(optional)</small> @else Supplier @endif
                                </label>
                                @if (in_array($type, ['sale', 'use'], true))
                                    <select name="customer_id" class="form-control">
                                        <option value="">—</option>
                                        @foreach ($customers as $c)
                                            <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" name="party" value="{{ old('party') }}" class="form-control" maxlength="255" placeholder="e.g. Shopno IT, Dhaka">
                                @endif
                            </div>
                        @endif
                        @if ($type === 'sale')
                            <div class="col-md-4 form-group">
                                <label>Buyer <small class="text-muted">(if not a customer)</small></label>
                                <input type="text" name="party" value="{{ old('party') }}" class="form-control" maxlength="255">
                            </div>
                        @endif

                        <div class="col-md-4 form-group">
                            <label>Memo / invoice no</label>
                            <input type="text" name="reference" value="{{ old('reference') }}" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-8 form-group">
                            <label>Serial numbers / MAC</label>
                            <input type="text" name="serials" value="{{ old('serials') }}" class="form-control" maxlength="5000" placeholder="optional — separate with commas">
                        </div>
                        <div class="col-12 form-group mb-0">
                            <label>Note</label>
                            <input type="text" name="note" value="{{ old('note') }}" class="form-control" maxlength="1000" placeholder="optional">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if (in_array($type, ['purchase', 'sale'], true))
                <div class="card acct-panel">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-link mr-1 text-primary"></i> Accounts</h3></div>
                    <div class="card-body">
                        <div class="custom-control custom-switch mb-3">
                            <input type="hidden" name="to_accounts" value="0">
                            <input type="checkbox" class="custom-control-input" id="to-accounts" name="to_accounts" value="1" @checked(old('to_accounts'))>
                            <label class="custom-control-label" for="to-accounts">Also record in Income &amp; Expenses</label>
                        </div>
                        <div id="acct-fields" @style(['display:none' => ! old('to_accounts')])>
                            <div class="form-group">
                                <label>{{ \App\Support\Ui::t($type === 'sale' ? 'Income head' : 'Expense head') }}</label>
                                <select name="transaction_category_id" class="form-control">
                                    @foreach ($type === 'sale' ? $incomeHeads : $expenseHeads as $h)
                                        <option value="{{ $h->id }}" @selected(old('transaction_category_id', $type === 'sale' ? $defaultIncome : $defaultExpense) == $h->id)>{{ $h->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if ($type === 'purchase')
                                <div class="form-group mb-0">
                                    <label>Paid from</label>
                                    <select name="account" class="form-control">
                                        @foreach (Transaction::ACCOUNTS as $k => $accLabel)
                                            <option value="{{ $k }}" @selected(old('account', 'bank') === $k)>{{ \App\Support\Ui::t($accLabel) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <p class="small text-muted mb-0"><i class="fas fa-university"></i> Sale money is income, so it's recorded in the bank.</p>
                            @endif
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            Turn this on only if the money isn't entered in the Cash Book / Income &amp; Expenses already — otherwise it would be counted twice.
                        </p>
                    </div>
                </div>
            @endif

            <div class="card acct-panel">
                <div class="card-body">
                    <button class="btn btn-block btn-lg text-white" style="background: {{ $typeColor }}"><i class="fas fa-save"></i> Save {{ strtolower(\App\Support\Ui::t($typeLabel)) }}</button>
                    <a href="{{ route('inventory.entries.index') }}" class="btn btn-block btn-light">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

@stop

@section('js')
<script>
(function () {
    var items = @json($itemData);
    var type = @json($type);
    var t = {
        store: @json(\App\Support\Ui::t('In the store')),
        avg: @json(\App\Support\Ui::t('average cost')),
        at: @json(\App\Support\Ui::t('In use at')),
        none: @json(\App\Support\Ui::t('Not in use anywhere')),
    };
    var $item = $('#inv-item'), $qty = $('#inv-qty');
    var tk = function (v) { return '৳' + Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); };
    var num = function (v) { return Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); };

    function refresh() {
        var d = items[$item.val()];
        $('#inv-unit').text(d ? d.unit : 'pcs');
        $('#inv-stock-hint').html(d ? '<i class="fas fa-warehouse"></i> ' + t.store + ': <b>' + num(d.stock) + ' ' + d.unit + '</b>' + (d.avg ? ' · ' + t.avg + ' ' + tk(d.avg) : '') : '&nbsp;');
        if (d && type === 'sale' && !$('#inv-price').val() && d.price) { $('#inv-price').val(d.price); }
        if (d && ['use', 'adjust_out', 'sale'].indexOf(type) !== -1) { $qty.attr('max', d.stock > 0 ? d.stock : null); }
        var $p = $('#inv-places').empty();
        if (d && (type === 'return' || type === 'damage')) {
            if (!d.places.length) { $p.append('<small class="text-muted">' + t.none + '</small>'); }
            d.places.forEach(function (pl) {
                $('<button type="button" class="btn btn-xs btn-outline-primary mr-1 mb-1"></button>')
                    .text(pl.location + ' · ' + num(pl.qty) + ' ' + d.unit)
                    .on('click', function () { $('#inv-location').val(pl.location); })
                    .appendTo($p);
            });
        }
        total();
    }
    function total() {
        var price = $('#inv-cost').val() || $('#inv-price').val();
        $('#inv-total').text(tk((parseFloat($qty.val()) || 0) * (parseFloat(price) || 0)));
    }
    $item.on('change', refresh);
    $('#inv-qty, #inv-cost, #inv-price').on('input', total);
    $('#to-accounts').on('change', function () { $('#acct-fields').toggle(this.checked); });
    refresh();
})();
</script>
@stop
