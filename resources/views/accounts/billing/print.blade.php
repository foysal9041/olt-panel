@extends('layouts.accounts-print')

{{--
    Bandwidth client invoice on the company pad, in the office's format:
    Particular · Billing Date · Rate · Mbps · Amount · Remarks, Total Bill,
    previous month due, Total MRC, then each payment with what is still due.
--}}

@use('App\Support\NumberWords')
@php
    $plain = request()->boolean('plain');
    $money = fn ($v) => number_format((float) $v, 2);
    $trim = fn ($v) => $v === null || $v === '' ? '' : (rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.') ?: '0');
    $first = $invoices->first();
    $pdfName = $invoices->count() === 1
        ? str_replace('/', '-', $first->invoice_no) . ' ' . $first->customer->name
        : 'Sunlit DC Bandwidth Invoices ' . $month->format('M Y');
    $pdfName = trim(preg_replace('/[^\w\s.()-]+/u', ' ', $pdfName)) . '.pdf';
@endphp

@section('title', $invoices->count() === 1 ? $first->invoice_no : 'Bandwidth invoices ' . $month->format('F Y'))
@section('back', $invoices->count() === 1 ? route('accounts.billing.show', $first) : route('accounts.billing.index', ['month' => $month->format('Y-m')]))
@section('bare', '1')

@section('actions')
    <button type="button" id="pdf-btn" style="background:#15803d; border-color:#15803d">⬇ Download PDF</button>
    <a href="{{ request()->fullUrlWithQuery(['plain' => $plain ? null : 1, 'download' => null]) }}">{{ $plain ? 'Show pad design' : 'Pre-printed pad (blank)' }}</a>
@endsection

@section('styles')
    body { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; }
    .sheet { max-width: 210mm; padding: 0; background: transparent; box-shadow: none; }
    .inv { flex: 1; display: flex; flex-direction: column; font-size: 11pt; }
    .inv-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8mm; }
    .inv-meta { line-height: 1.55; font-weight: 700; }
    .inv-tag { font-size: 20pt; font-weight: 700; letter-spacing: .12em; color: #0d5fc0; line-height: 1; }
    .bill-for { margin: 0 0 3mm; text-align: center; font-size: 14pt; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
    table.bw { width: 100%; border-collapse: collapse; }
    table.bw th, table.bw td { border: 1px solid #000; padding: .9mm 2mm; text-align: center; color: #000; background: transparent; font-size: 10pt; line-height: 1.25; }
    table.bw th { font-weight: 700; background: rgba(21, 101, 192, .09); }
    table.bw td.amt { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    table.bw tr.tot td { font-weight: 700; }
    table.bw tr.mrc td { font-weight: 700; background: rgba(21, 101, 192, .09); }
    table.bw tr.due td, table.bw td.red { color: #dc2626; }
    .words { margin: 5mm 0 0; font-weight: 700; }
    .pay-note { margin: 2mm 0 0; font-weight: 700; }
    .inv-signs { display: flex; justify-content: space-between; margin-top: auto; padding-top: 8mm; }
    .inv-sign { width: 64mm; text-align: center; }
    .inv-sign .role { margin: 0 0 13mm; }
    .inv-sign .line { border-top: 1.5px dotted #000; margin-bottom: 1.8mm; }
    .inv-sign div { line-height: 1.45; }
@endsection

@section('content')
    @foreach ($invoices as $invoice)
        @php
            $f = $invoice->figures();
            $customer = $invoice->customer;
            $to = $customer->contact_person ?: $customer->name;
            $running = $f['mrc'];
            $prevMonth = $invoice->month->copy()->subMonthNoOverflow()->format('F');
        @endphp
        <x-print.pad :plain="$plain">
            <div class="inv">
                <div class="inv-top">
                    <div class="inv-meta">
                        <div>Invoice No: {{ $invoice->invoice_no }}</div>
                        <div>Date: {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                        <div>To: {{ $to }}</div>
                        @if ($customer->contact_person)<div style="font-weight:400">{{ $customer->name }}</div>@endif
                    </div>
                    <div class="inv-tag">INVOICE</div>
                </div>

                <h3 class="bill-for">Bill for {{ $invoice->month->format('F Y') }}</h3>

                <table class="bw">
                    <thead>
                        <tr><th>Particular</th><th>Billing Date</th><th>Rate</th><th>Mbps</th><th>Amount</th><th>Remarks</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td>{{ $line->label }}</td>
                                <td>{{ $line->periodLabel() }}</td>
                                <td>{{ $line->mbps === null ? '' : $trim($line->rate) }}</td>
                                <td>{{ $trim($line->mbps) }}</td>
                                <td class="amt">{{ $money($line->amount) }}</td>
                                <td>{{ $line->remark }}</td>
                            </tr>
                        @endforeach
                        <tr class="tot"><td>Total Bill</td><td></td><td></td><td></td><td class="amt">{{ $money($f['bill']) }}</td><td></td></tr>
                        @if ((float) $f['previous'] != 0)
                            <tr class="due"><td>{{ (float) $f['previous'] > 0 ? 'Previous month due' : 'Advance from before' }}</td><td>Due {{ $prevMonth }}</td><td></td><td></td><td class="amt">{{ $money($f['previous']) }}</td><td></td></tr>
                        @endif
                        <tr class="mrc"><td>Total MRC</td><td></td><td></td><td></td><td class="amt">{{ $money($f['mrc']) }}</td><td></td></tr>
                        @foreach ($invoice->payments as $p)
                            @php $running = \App\Support\Dec::sub($running, $p->amount); @endphp
                            <tr>
                                <td>{{ $p->paid_on->format('d/m/y') }}</td>
                                <td>{{ $p->method }}{{ $p->note ? ' — ' . $p->note : '' }}</td>
                                <td></td><td></td>
                                <td class="amt">{{ $money($p->amount) }}</td>
                                <td>{{ $loop->last ? 'Total Paid-' . $money($f['paid']) : '' }}</td>
                            </tr>
                            <tr class="due"><td>Due</td><td></td><td></td><td></td><td class="amt">{{ $money($running) }}</td><td></td></tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($invoice->payments->isNotEmpty())
                    <p class="words">Due In word: {{ NumberWords::international(max(0, (float) $f['due'])) }}</p>
                @else
                    <p class="words">In word: {{ NumberWords::international($f['mrc']) }}</p>
                @endif
                @if ($invoice->due_date && (float) $f['due'] > 0)
                    <p class="pay-note">Note: Please pay your bill by {{ $invoice->due_date->format('j F Y') }}.</p>
                @endif
                @if ($invoice->notes)<p class="pay-note" style="font-weight:400">{{ $invoice->notes }}</p>@endif

                <div class="inv-signs">
                    <div class="inv-sign">
                        <p class="role">Prepared By</p>
                        <div class="line"></div>
                        <div>{{ $invoice->prepared_by }}</div>
                        @if ($invoice->prepared_title)<div>{{ $invoice->prepared_title }}</div>@endif
                        <div>Sunlit Network DC</div>
                    </div>
                    <div class="inv-sign">
                        <p class="role">Received By</p>
                        <div class="line"></div>
                        <div>{{ $to }}</div>
                        @if ($customer->contact_person)<div>{{ $customer->name }}</div>@endif
                    </div>
                </div>
            </div>
        </x-print.pad>
    @endforeach
@endsection

@section('scripts')
    @include('accounts.partials.pdf-download')
@endsection
