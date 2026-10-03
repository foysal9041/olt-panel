@extends('layouts.accounts-print')

{{--
    Customer invoice printed on the company pad (<x-print.pad>, one A4 page
    each) in the office's own invoice layout — Total Payment · Net Bill ·
    Total Payable, amount in words, Prepared By / Received By (the linked
    customer's name, when the customer record has one). The bKash
    charge stays internal (settlement page and Excel), not on the invoice.
    ?plain=1 hides the pad artwork (same spacing) for pre-printed pad paper.
      Net Bill       = Payment Difference − bKash Charge
      Total Payable  = Total Payment + Deduction
--}}

@use('App\Support\Dec')
@php
    $plain = request()->boolean('plain');
    $n = fn ($v) => number_format((float) Dec::round($v, 2), 2);
    $pdfName = $rows->count() === 1
        ? str_replace('/', '-', $rows->first()->invoice_no) . ' ' . $rows->first()->displayName()
        : 'Sunlit DC Invoices ' . $settlement->month->format('M Y');
    $pdfName = trim(preg_replace('/[^\w\s.()-]+/u', ' ', $pdfName)) . '.pdf';
@endphp

@section('title', $rows->count() === 1 ? $rows->first()->invoice_no : 'Invoices ' . $settlement->month->format('F Y'))
@section('back', route('accounts.settlements.show', $settlement))
@section('bare', '1')

@section('actions')
    <button type="button" id="pdf-btn" style="background:#15803d; border-color:#15803d">⬇ Download PDF</button>
    <a href="{{ request()->fullUrlWithQuery(['plain' => $plain ? null : 1, 'download' => null]) }}">
        {{ $plain ? 'Show pad design' : 'Pre-printed pad (blank)' }}
    </a>
@endsection

@section('styles')
    :root { --blue: #0d5fc0; --blue-soft: rgba(21, 101, 192, .09); }
    body { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; }
    .sheet { max-width: 210mm; padding: 0; background: transparent; box-shadow: none; }

    /* Invoice body */
    .inv { flex: 1; display: flex; flex-direction: column; padding: 0 1mm; font-size: 12pt; }
    .inv-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 11mm; }
    .inv-meta { line-height: 1.6; }
    .inv-meta b { font-weight: 700; }
    .inv-tag { font-size: 20pt; font-weight: 700; letter-spacing: .12em; color: var(--blue); line-height: 1; }
    .bill-period { margin: -2.5mm 0 4mm; text-align: center; font-size: 10.5pt; color: #334155; }
    .bill-for { margin: 0 0 4mm; text-align: center; font-size: 15pt; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
    table.bill { width: 100%; border-collapse: collapse; }
    table.bill th, table.bill td { border: 1px solid #000; padding: 1.6mm 3mm; text-align: center; background: transparent; color: #000; font-size: 11.5pt; }
    table.bill th { font-weight: 700; background: var(--blue-soft); color: #0b3f86; }
    table.bill th:first-child, table.bill td:first-child { width: 14mm; font-weight: 700; }
    table.bill td.part { width: 62mm; font-weight: 700; }
    table.bill td.amt { font-variant-numeric: tabular-nums; }
    table.bill tr.payable td { font-weight: 700; background: var(--blue-soft); }
    .words { margin: 9mm 0 0; }
    .words b { font-weight: 700; }
    /* Two equal signature blocks: left edge on the table's left, right edge on its right. */
    .inv-signs { display: flex; justify-content: space-between; align-items: flex-start; margin-top: auto; padding-top: 12mm; }
    .inv-sign { width: 64mm; text-align: center; }
    .inv-sign .role { font-weight: 700; text-decoration: underline; text-underline-offset: 2px; margin: 0 0 15mm; }
    .inv-sign .line { border-top: 1.5px dotted #000; margin-bottom: 1.8mm; }
    .inv-sign div { line-height: 1.45; }
@endsection

@section('content')
    @foreach ($rows as $row)
        @php $c = $row->calc($settlement->bkash_percent); @endphp
        <x-print.pad :plain="$plain">
            <div class="inv">
                <div class="inv-top">
                    <div class="inv-meta">
                        <div><b>Invoice No: {{ $row->invoice_no }}</b></div>
                        <div><b>Date: {{ $settlement->invoice_date->format('d/m/Y') }}</b></div>
                        <div><b>To:</b> {{ $row->name }}</div>
                    </div>
                    <div class="inv-tag">INVOICE</div>
                </div>

                <h3 class="bill-for">Bill for {{ $settlement->month->format('M Y') }}</h3>
                <p class="bill-period">Billing period: {{ $settlement->periodLabel('d/m/Y') }}</p>

                <table class="bill">
                    <thead>
                        <tr><th>SN</th><th>Particular</th><th>Amount</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>1</td><td class="part">Total Payment</td><td class="amt">{{ $n($c['payment']) }}</td></tr>
                        <tr><td>2</td><td class="part">Net Bill</td><td class="amt">{{ $n($c['income']) }}</td></tr>
                        <tr class="payable"><td></td><td class="part">Total Payable</td><td class="amt">{{ $n($c['invoice']) }}</td></tr>
                    </tbody>
                </table>

                <p class="words"><b>In word:</b> {{ \App\Support\NumberWords::international($c['invoice']) }}</p>

                <div class="inv-signs">
                    <div class="inv-sign">
                        <p class="role">Prepared By</p>
                        <div class="line"></div>
                        <div>{{ $settlement->prepared_by }}</div>
                        @if ($settlement->prepared_title)<div>{{ $settlement->prepared_title }}</div>@endif
                        <div>Sunlit Network DC</div>
                    </div>
                    <div class="inv-sign">
                        <p class="role">Received By</p>
                        <div class="line"></div>
                        @if (filled($row->customer?->name))<div>{{ $row->customer->name }}</div>@endif
                    </div>
                </div>
            </div>
        </x-print.pad>
    @endforeach
@endsection

@section('scripts')
    @include('accounts.partials.pdf-download')
@endsection
