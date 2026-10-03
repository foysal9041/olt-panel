@extends('adminlte::page')

@section('title', $invoice->invoice_no)

@use('App\Models\BandwidthPayment')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $trim = fn ($v) => $v === null || $v === '' ? '' : (rtrim(rtrim((string) $v, '0'), '.') ?: '0');
    $customer = $invoice->customer;
    $isAdmin = auth()->user()->isAdmin();
    $due = (float) $figures['due'];
    $dim = $invoice->month->daysInMonth;
@endphp

@section('content_header')
<x-accounts.header :title="$invoice->invoice_no . ' — ' . $customer->name" icon="fas fa-file-invoice-dollar"
    :subtitle="'Bill for ' . $invoice->month->format('F Y') . ' · dated ' . $invoice->invoice_date->format('d M Y')" :back="route('accounts.billing.index', ['month' => $invoice->month->format('Y-m')])">
    <a href="{{ route('accounts.billing.print', $invoice) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route('accounts.billing.print', [$invoice, 'download' => 1]) }}" target="_blank" class="btn btn-success btn-sm"><i class="fas fa-file-pdf"></i> Download PDF</a>
</x-accounts.header>
@stop

@section('css')
<style>
    .bi-table th { font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-top: 0; white-space: nowrap; }
    .bi-table td { vertical-align: middle; padding: .35rem .35rem; }
    .bi-table .form-control { height: calc(1.6em + .45rem + 2px); padding: .2rem .4rem; font-size: .86rem; }
    .bi-table input[type=number] { text-align: right; }
    .bi-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .bi-sum .acct-list-row { padding: .55rem 1.1rem; }
    .bi-sum .lbl { flex: 1; font-size: .88rem; color: #475569; }
    .bi-sum .val { font-weight: 700; font-variant-numeric: tabular-nums; }
</style>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center flex-wrap" style="gap:.5rem">
        <span class="mr-auto">{{ session('success') }}</span>
        @if (session('receipt') && ($r = $invoice->payments->firstWhere('id', session('receipt'))))
            <a href="{{ route('accounts.billing.payments.receipt', [$invoice, $r]) }}" target="_blank" class="btn btn-sm btn-light"><i class="fas fa-print"></i> Print receipt</a>
            <a href="{{ route('accounts.billing.payments.receipt', [$invoice, $r, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-light text-success"><i class="fas fa-file-pdf"></i> Receipt PDF</a>
        @endif
    </div>
@endif
@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
@if ($closed)
    <div class="alert alert-light border small"><i class="fas fa-lock text-success"></i> <strong>{{ $invoice->month->format('F Y') }} is closed</strong> (Net Profit finalized) — this invoice can't be changed until an admin reopens the month.</div>
@endif

<div class="row">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('accounts.billing.update', $invoice) }}" id="bi-form">
            @csrf @method('PUT')
            <fieldset @disabled($closed)>
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-1 text-primary"></i> Bill for {{ $invoice->month->format('F Y') }}</h3>
                    <span class="small text-muted">Full month = Rate × Mbps · part of a month = its share of {{ $dim }} days</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table bi-table mb-0">
                            <thead>
                                <tr><th class="pl-3">Particular</th><th>From</th><th>To</th><th class="bi-num">Rate</th><th class="bi-num">Mbps</th><th class="bi-num">Amount</th><th>Remarks</th><th></th></tr>
                            </thead>
                            <tbody id="bi-lines">
                                @foreach ($invoice->lines as $i => $line)
                                    <tr>
                                        <td class="pl-3" style="min-width:7rem">
                                            <input type="hidden" name="lines[{{ $i }}][bandwidth_type_id]" value="{{ $line->bandwidth_type_id }}">
                                            <input type="text" name="lines[{{ $i }}][label]" value="{{ $line->label }}" class="form-control" maxlength="100" required>
                                        </td>
                                        <td><input type="date" name="lines[{{ $i }}][period_from]" value="{{ $line->period_from?->toDateString() }}" class="form-control js-calc"></td>
                                        <td><input type="date" name="lines[{{ $i }}][period_to]" value="{{ $line->period_to?->toDateString() }}" class="form-control js-calc"></td>
                                        <td style="width:6rem"><input type="number" name="lines[{{ $i }}][rate]" value="{{ $trim($line->rate) }}" step="any" min="0" class="form-control js-calc"></td>
                                        <td style="width:6rem"><input type="number" name="lines[{{ $i }}][mbps]" value="{{ $trim($line->mbps) }}" step="any" min="0" class="form-control js-calc"></td>
                                        <td style="width:8.5rem"><input type="number" name="lines[{{ $i }}][amount]" value="{{ $trim($line->amount) }}" step="any" class="form-control js-amt"></td>
                                        <td><input type="text" name="lines[{{ $i }}][remark]" value="{{ $line->remark }}" class="form-control" maxlength="255"></td>
                                        <td class="text-center"><button type="button" class="btn btn-link btn-sm p-0 text-muted js-del" title="Remove"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="5" class="pl-3 font-weight-bold">Total Bill</td><td class="bi-num font-weight-bold" id="bi-total">{{ $money($figures['bill']) }}</td><td colspan="2"></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @unless ($closed)
                    <div class="card-footer d-flex flex-wrap align-items-center" style="gap:.5rem">
                        <button type="button" class="btn btn-link btn-sm p-0 js-add"><i class="fas fa-plus"></i> Add line</button>
                        <span class="small text-muted">e.g. a one-time charge, or a discount with a minus amount</span>
                    </div>
                @endunless
            </div>

            <div class="card acct-panel">
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-md-3 form-group"><label class="small font-weight-bold">Invoice date</label><input type="date" name="invoice_date" value="{{ $invoice->invoice_date->toDateString() }}" class="form-control form-control-sm" required></div>
                        <div class="col-md-3 form-group"><label class="small font-weight-bold">Pay by</label><input type="date" name="due_date" value="{{ $invoice->due_date?->toDateString() }}" class="form-control form-control-sm"></div>
                        <div class="col-md-3 form-group"><label class="small font-weight-bold">Prepared by</label><input type="text" name="prepared_by" value="{{ $invoice->prepared_by }}" class="form-control form-control-sm" maxlength="100"></div>
                        <div class="col-md-3 form-group"><label class="small font-weight-bold">Title</label><input type="text" name="prepared_title" value="{{ $invoice->prepared_title }}" class="form-control form-control-sm" maxlength="100"></div>
                    </div>
                    <div class="form-group"><label class="small font-weight-bold">Note on the invoice</label><input type="text" name="notes" value="{{ $invoice->notes }}" class="form-control form-control-sm" maxlength="500" placeholder="optional"></div>
                    @unless ($closed)
                        <div class="d-flex flex-wrap justify-content-end" style="gap:.5rem">
                            @if ($isAdmin && $invoice->payments->isEmpty())
                                <button type="submit" form="bi-delete" class="btn btn-link text-danger mr-auto"><i class="fas fa-trash-alt"></i> Delete invoice</button>
                            @endif
                            <button type="submit" form="bi-recalc" class="btn btn-outline-secondary"><i class="fas fa-sync-alt"></i> Work out again from rates</button>
                            <button class="btn btn-primary"><i class="fas fa-save"></i> Save invoice</button>
                        </div>
                    @endunless
                </div>
            </div>
            </fieldset>
        </form>
        <form method="POST" action="{{ route('accounts.billing.recalculate', $invoice) }}" id="bi-recalc" class="js-confirm-delete"
              data-confirm-message="Replace the lines with what the client's rates and Mbps give for {{ $invoice->month->format('F Y') }}? Hand edits are lost; payments stay.">@csrf</form>
        @if ($isAdmin)
            <form method="POST" action="{{ route('accounts.billing.destroy', $invoice) }}" id="bi-delete" class="js-confirm-delete" data-confirm-message="Delete invoice {{ $invoice->invoice_no }}?">@csrf @method('DELETE')</form>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="acct-stat acct-stat-hero mb-3">
            <div class="acct-stat-label">{{ $due > 0 ? 'Due' : 'Paid up' }} <i class="fas fa-wallet"></i></div>
            <div class="acct-stat-value">৳{{ $money(max(0, $due)) }}</div>
            <div class="acct-stat-foot">@if ($due < 0) Advance ৳{{ $money(-$due) }} · @endif{{ $customer->name }}</div>
        </div>

        <div class="card acct-panel bi-sum">
            <div class="card-body p-0">
                <div class="acct-list-row"><span class="lbl">Total Bill</span><span class="val">{{ $money($figures['bill']) }}</span></div>
                <div class="acct-list-row"><span class="lbl">Previous due</span><span class="val {{ (float) $figures['previous'] > 0 ? 'text-danger' : '' }}">{{ $money($figures['previous']) }}</span></div>
                <div class="acct-list-row" style="background:#f8fafc"><span class="lbl font-weight-bold" style="color:#0f172a">Total MRC</span><span class="val">{{ $money($figures['mrc']) }}</span></div>
                <div class="acct-list-row"><span class="lbl">Paid</span><span class="val text-income">{{ $money($figures['paid']) }}</span></div>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-hand-holding-usd mr-1 text-success"></i> Payments</h3></div>
            <div class="card-body p-0">
                @forelse ($invoice->payments as $p)
                    <div class="acct-list-row py-2">
                        <span class="acct-list-main">
                            <div class="acct-list-title" style="font-size:.88rem">{{ $p->paid_on->format('d M Y') }} · {{ $p->method }} <span class="text-muted font-weight-normal small">{{ $p->receiptNo() }}</span></div>
                            <div class="acct-list-sub">
                                Received by {{ $p->received_by ?: '—' }}{{ $p->reference ? ' · Ref ' . $p->reference : '' }}{{ $p->note ? ' · ' . $p->note : '' }}
                                
                            </div>
                        </span>
                        <span class="acct-list-amount text-income">{{ $money($p->amount) }}</span>
                        <a href="{{ route('accounts.billing.payments.receipt', [$invoice, $p]) }}" target="_blank" class="btn btn-link btn-sm p-0 ml-1" title="Money receipt"><i class="fas fa-receipt"></i></a>
                        @if ($isAdmin && ! $closed)
                            <form method="POST" action="{{ route('accounts.billing.payments.destroy', [$invoice, $p]) }}" class="js-confirm-delete ml-1" data-confirm-message="Remove this payment{{ $p->transaction_id ? ' (and its Cash Book entry)' : '' }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-link btn-sm p-0 text-danger"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="acct-empty py-3"><i class="fas fa-receipt"></i>No payment yet.</div>
                @endforelse
            </div>
            <form method="POST" action="{{ route('accounts.billing.payments.store', $invoice) }}" class="card-footer">
                @csrf
                <div class="form-row">
                    <div class="col-6 form-group mb-2"><label class="small font-weight-bold mb-0">Date</label><input type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                    <div class="col-6 form-group mb-2"><label class="small font-weight-bold mb-0">Received by</label>
                        <select name="method" class="form-control form-control-sm" required>
                            @foreach (BandwidthPayment::METHODS as $m)<option @selected(old('method', 'Bank') === $m)>{{ $m }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group mb-2"><label class="small font-weight-bold mb-0">Amount</label>
                    <div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                        <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" placeholder="{{ $due > 0 ? number_format($due, 2, '.', '') : '0.00' }}" class="form-control" required></div>
                </div>
                <div class="form-row">
                    <div class="col-6 form-group mb-2"><label class="small font-weight-bold mb-0">Received by</label>
                        <input type="text" name="received_by" value="{{ old('received_by', auth()->user()->name) }}" list="bi-staff" maxlength="100" class="form-control form-control-sm" required>
                        <datalist id="bi-staff">@foreach (\App\Models\User::orderBy('name')->pluck('name') as $n)<option value="{{ $n }}">@endforeach</datalist>
                    </div>
                    <div class="col-6 form-group mb-2"><label class="small font-weight-bold mb-0">Reference / TrxID</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" maxlength="100" class="form-control form-control-sm" placeholder="optional">
                    </div>
                </div>
                <div class="form-group mb-2"><input type="text" name="note" value="{{ old('note') }}" maxlength="255" class="form-control form-control-sm" placeholder="Note, e.g. BK to Bank"></div>
                <button class="btn btn-success btn-sm btn-block"><i class="fas fa-check"></i> Record payment</button>
                <small class="text-muted d-block mt-1">Paid two ways at once (e.g. Bank 7,500 + Cash 12,000)? Record each separately. All of it is deposited in the bank (it doesn't go into the petty cash). Each payment gets a money receipt.</small>
            </form>
        </div>
    </div>
</div>

<template id="bi-line">
    <tr>
        <td class="pl-3"><input type="hidden" data-name="bandwidth_type_id" value=""><input type="text" data-name="label" class="form-control" maxlength="100" required placeholder="Particular"></td>
        <td><input type="date" data-name="period_from" class="form-control js-calc" value="{{ $invoice->month->toDateString() }}"></td>
        <td><input type="date" data-name="period_to" class="form-control js-calc" value="{{ $invoice->month->copy()->endOfMonth()->toDateString() }}"></td>
        <td><input type="number" data-name="rate" step="any" min="0" class="form-control js-calc"></td>
        <td><input type="number" data-name="mbps" step="any" min="0" class="form-control js-calc"></td>
        <td><input type="number" data-name="amount" step="any" class="form-control js-amt"></td>
        <td><input type="text" data-name="remark" class="form-control" maxlength="255"></td>
        <td class="text-center"><button type="button" class="btn btn-link btn-sm p-0 text-muted js-del" title="Remove"><i class="fas fa-times"></i></button></td>
    </tr>
</template>

@stop

@section('js')
@unless ($closed)
<script>
(function () {
    var body = document.getElementById('bi-lines'), counter = {{ $invoice->lines->count() }}, dim = {{ $dim }};
    var DAY = 86400000;

    function total() {
        var t = 0;
        body.querySelectorAll('.js-amt').forEach(function (i) { t += Number(i.value) || 0; });
        document.getElementById('bi-total').textContent = t.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Rate × Mbps for the whole month; a part of the month is its share of the days.
    function calc(tr) {
        var get = function (n) { return tr.querySelector('[name$="[' + n + ']"]'); };
        var rate = Number(get('rate').value), mbps = get('mbps').value === '' ? 1 : Number(get('mbps').value);
        if (!rate) return;
        var from = get('period_from').value, to = get('period_to').value, share = 1;
        if (from && to) {
            var days = Math.round((Date.parse(to) - Date.parse(from)) / DAY) + 1;
            if (days > 0 && days < dim) share = days / dim;
        }
        get('amount').value = Math.round(rate * mbps * share * 10000) / 10000;
    }

    body.addEventListener('input', function (e) {
        if (e.target.classList.contains('js-calc')) calc(e.target.closest('tr'));
        total();
    });
    body.addEventListener('click', function (e) {
        var del = e.target.closest('.js-del');
        if (del) { del.closest('tr').remove(); total(); }
    });
    document.querySelector('.js-add').addEventListener('click', function () {
        var tr = document.getElementById('bi-line').content.firstElementChild.cloneNode(true), n = counter++;
        tr.querySelectorAll('[data-name]').forEach(function (i) { i.name = 'lines[' + n + '][' + i.dataset.name + ']'; });
        body.appendChild(tr);
        tr.querySelector('[data-name="label"]').focus();
    });
})();
</script>
@endunless
@stop
