@extends('layouts.accounts-print')

{{-- Money receipt for one payment from a bandwidth client, on the company pad. --}}

@use('App\Support\NumberWords')
@php
    $plain = request()->boolean('plain');
    $money = fn ($v) => number_format((float) $v, 2);
    $customer = $invoice->customer;
    $from = $customer->contact_person ? $customer->contact_person . ' (' . $customer->name . ')' : $customer->name;
    $pdfName = trim(preg_replace('/[^\w\s.()-]+/u', ' ', $payment->receiptNo() . ' ' . $customer->name)) . '.pdf';
@endphp

@section('title', 'Money Receipt ' . $payment->receiptNo())
@section('back', route('accounts.billing.show', $invoice))
@section('bare', '1')

@section('actions')
    <button type="button" id="pdf-btn" style="background:#15803d; border-color:#15803d">⬇ Download PDF</button>
    <a href="{{ request()->fullUrlWithQuery(['plain' => $plain ? null : 1, 'download' => null]) }}">{{ $plain ? 'Show pad design' : 'Pre-printed pad (blank)' }}</a>
@endsection

@section('styles')
    body { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; }
    .sheet { max-width: 210mm; padding: 0; background: transparent; box-shadow: none; }
    .rc { flex: 1; display: flex; flex-direction: column; font-size: 12pt; color: #000; }
    .rc-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 9mm; }
    .rc-meta { line-height: 1.6; font-weight: 700; }
    .rc-tag { font-size: 19pt; font-weight: 800; letter-spacing: .1em; color: #0d5fc0; line-height: 1.1; text-align: right; }
    .rc-tag small { display: block; font-size: 9pt; letter-spacing: .02em; color: #475569; font-weight: 600; }
    .rc-amount { margin: 0 auto 7mm; padding: 5mm 10mm; border: 2px solid #0d5fc0; border-radius: 3mm; text-align: center; background: rgba(21, 101, 192, .06); }
    .rc-amount span { display: block; font-size: 10pt; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #334155; }
    .rc-amount b { display: block; font-size: 24pt; font-variant-numeric: tabular-nums; }
    table.rc-t { width: 100%; border-collapse: collapse; }
    table.rc-t td { border: 1px solid #000; padding: 2mm 3mm; font-size: 11pt; text-align: left; background: transparent; color: #000; }
    table.rc-t td.l { width: 42%; font-weight: 700; background: rgba(21, 101, 192, .06); }
    table.rc-t td.n { font-variant-numeric: tabular-nums; }
    .rc-words { margin: 6mm 0 0; font-weight: 700; }
    .rc-thanks { margin: 3mm 0 0; color: #334155; }
    .inv-signs { display: flex; justify-content: space-between; margin-top: auto; padding-top: 10mm; }
    .inv-sign { width: 64mm; text-align: center; }
    .inv-sign .line { border-top: 1.5px dotted #000; margin: 14mm 0 1.8mm; }
    .inv-sign div { line-height: 1.45; }
@endsection

@section('content')
    <x-print.pad :plain="$plain">
        <div class="rc">
            <div class="rc-top">
                <div class="rc-meta">
                    <div>Receipt No: {{ $payment->receiptNo() }}</div>
                    <div>Date: {{ $payment->paid_on->format('d/m/Y') }}</div>
                    <div>Invoice: {{ $invoice->invoice_no }}</div>
                </div>
                <div class="rc-tag">MONEY RECEIPT<small>Bill for {{ $invoice->month->format('F Y') }}</small></div>
            </div>

            <div class="rc-amount"><span>Amount received</span><b>৳ {{ $money($payment->amount) }}</b></div>

            <table class="rc-t">
                <tr><td class="l">Received with thanks from</td><td>{{ $from }}</td></tr>
                <tr><td class="l">Payment method</td><td>{{ $payment->method }}{{ $payment->note ? ' — ' . $payment->note : '' }}</td></tr>
                @if ($payment->reference)
                    <tr><td class="l">Reference / Transaction ID</td><td>{{ $payment->reference }}</td></tr>
                @endif
                <tr><td class="l">Against invoice</td><td>{{ $invoice->invoice_no }} — Bill for {{ $invoice->month->format('F Y') }}</td></tr>
                <tr><td class="l">Due after this payment</td><td class="n">৳ {{ $money(max(0, (float) $dueAfter)) }}{{ (float) $dueAfter < 0 ? ' (advance ৳ ' . $money(-(float) $dueAfter) . ')' : '' }}</td></tr>
                <tr><td class="l">Received by</td><td>{{ $payment->received_by ?: '—' }}</td></tr>
            </table>

            <p class="rc-words">In word: {{ NumberWords::international($payment->amount) }}</p>
            <p class="rc-thanks">Thank you for your payment.</p>

            <div class="inv-signs">
                <div class="inv-sign">
                    <div class="line"></div>
                    <div><strong>Received By</strong></div>
                    <div>{{ $payment->received_by ?: '' }}</div>
                    <div>Sunlit Network DC</div>
                </div>
                <div class="inv-sign">
                    <div class="line"></div>
                    <div><strong>Paid By</strong></div>
                    <div>{{ $customer->contact_person ?: $customer->name }}</div>
                    @if ($customer->contact_person)<div>{{ $customer->name }}</div>@endif
                </div>
            </div>
        </div>
    </x-print.pad>
@endsection

@section('scripts')
    @include('accounts.partials.pdf-download')
@endsection
