@extends('pdf.layout')

{{-- PDF of accounts/billing/receipt (money receipt for one payment) for email — same wording. --}}

@use('App\Support\NumberWords')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $customer = $invoice->customer;
    $from = $customer->contact_person ? $customer->contact_person . ' (' . $customer->name . ')' : $customer->name;
@endphp

@section('styles')
    .tag { font-size: 19pt; letter-spacing: 1.5pt; line-height: 1.1; }
    .amount { margin: 0 0 7mm; padding: 4mm 10mm; border: 2px solid #0d5fc0; border-radius: 3mm; text-align: center; background: #eef4fb; }
    .amount span { display: block; font-size: 10pt; font-weight: bold; letter-spacing: 1pt; color: #334155; }
    .amount b { display: block; font-size: 24pt; margin-top: 1mm; }
    table.rc { width: 100%; border-collapse: collapse; }
    table.rc td { border: 1px solid #000; padding: 2mm 3mm; font-size: 11pt; text-align: left; }
    table.rc td.l { width: 42%; font-weight: bold; background: #eef4fb; }
    .thanks { margin: 3mm 0 0; color: #334155; }
    .signs .line { margin-top: 14mm; }
@endsection

@section('content')
    <div class="pad last">
        @include('pdf.partials.pad')
        <table class="sheet"><tr><td class="sheet-body">
            <table class="top">
                <tr>
                    <td class="meta">
                        <div>Receipt No: {{ $payment->receiptNo() }}</div>
                        <div>Date: {{ $payment->paid_on->format('d/m/Y') }}</div>
                        <div>Invoice: {{ $invoice->invoice_no }}</div>
                    </td>
                    <td class="tag">MONEY RECEIPT<small>Bill for {{ $invoice->month->format('F Y') }}</small></td>
                </tr>
            </table>

            <div class="amount"><span>AMOUNT RECEIVED</span><b><span class="tk" style="display:inline;font-size:24pt;letter-spacing:0;color:#000">৳</span> {{ $money($payment->amount) }}</b></div>

            <table class="rc">
                <tr><td class="l">Received with thanks from</td><td>{{ $from }}</td></tr>
                <tr><td class="l">Payment method</td><td>{{ $payment->method }}{{ $payment->note ? ' — ' . $payment->note : '' }}</td></tr>
                @if ($payment->reference)
                    <tr><td class="l">Reference / Transaction ID</td><td>{{ $payment->reference }}</td></tr>
                @endif
                <tr><td class="l">Against invoice</td><td>{{ $invoice->invoice_no }} — Bill for {{ $invoice->month->format('F Y') }}</td></tr>
                <tr><td class="l">Due after this payment</td><td><span class="tk">৳</span> {{ $money(max(0, (float) $dueAfter)) }}@if ((float) $dueAfter < 0) (advance <span class="tk">৳</span> {{ $money(-(float) $dueAfter) }})@endif</td></tr>
                <tr><td class="l">Received by</td><td>{{ $payment->received_by ?: '—' }}</td></tr>
            </table>

            <p class="words">In word: {{ NumberWords::international($payment->amount) }}</p>
            <p class="thanks">Thank you for your payment.</p>
        </td></tr>
        <tr><td class="sheet-signs">
            <table class="signs"><tr>
                <td>
                    <div class="line"></div>
                    <div><strong>Received By</strong></div>
                    <div>{{ $payment->received_by ?: '' }}</div>
                    <div>Sunlit Network DC</div>
                </td>
                <td class="gap"></td>
                <td>
                    <div class="line"></div>
                    <div><strong>Paid By</strong></div>
                    <div>{{ $customer->contact_person ?: $customer->name }}</div>
                    @if ($customer->contact_person)<div>{{ $customer->name }}</div>@endif
                </td>
            </tr></table>
        </td></tr></table>
    </div>
@endsection
