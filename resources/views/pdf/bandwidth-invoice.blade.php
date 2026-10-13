@extends('pdf.layout')

{{-- PDF of accounts/billing/print (bandwidth client invoice) for email — same lines, figures and wording. --}}

@use('App\Support\Dec')
@use('App\Support\NumberWords')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $trim = fn ($v) => $v === null || $v === '' ? '' : (rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.') ?: '0');
@endphp

@section('styles')
    .top { margin-bottom: 8mm; }
    table.bw { width: 100%; border-collapse: collapse; }
    table.bw th, table.bw td { border: 1px solid #000; padding: .6mm 2mm; text-align: center; font-size: 10pt; line-height: 1.05; }
    table.bw th { font-weight: bold; background: #e9f0f9; }
    table.bw td.amt { text-align: right; white-space: nowrap; }
    table.bw tr.tot td { font-weight: bold; }
    table.bw tr.mrc td { font-weight: bold; background: #e9f0f9; }
    table.bw tr.due td { color: #dc2626; }
    .words { margin-top: 4mm; }
    .pay-note { margin: 2mm 0 0; font-weight: bold; font-size: 11pt; }
    .signs .role { text-decoration: none; font-weight: normal; margin-bottom: 11mm; }
    .meta { line-height: 1.2; }
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
        <div @class(['pad', 'last' => $loop->last])>
            @include('pdf.partials.pad')
            <table class="sheet"><tr><td class="sheet-body" style="font-size:11pt">
                <table class="top">
                    <tr>
                        <td class="meta">
                            <div>Invoice No: {{ $invoice->invoice_no }}</div>
                            <div>Date: {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                            <div>To: {{ $to }}</div>
                            @if ($customer->contact_person)<div style="font-weight:normal">{{ $customer->name }}</div>@endif
                        </td>
                        <td class="tag">INVOICE</td>
                    </tr>
                </table>

                <div class="bill-for">Bill for {{ $invoice->month->format('F Y') }}</div>

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
                            @php $running = Dec::sub($running, $p->amount); @endphp
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
                @if ($invoice->notes)<p class="pay-note" style="font-weight:normal">{{ $invoice->notes }}</p>@endif
            </td></tr>
            <tr><td class="sheet-signs">
                <table class="signs"><tr>
                    <td>
                        <p class="role">Prepared By</p>
                        <div class="line"></div>
                        <div>{{ $invoice->prepared_by }}</div>
                        @if ($invoice->prepared_title)<div>{{ $invoice->prepared_title }}</div>@endif
                        <div>Sunlit Network DC</div>
                    </td>
                    <td class="gap"></td>
                    <td>
                        <p class="role">Received By</p>
                        <div class="line"></div>
                        <div>{{ $to }}</div>
                        @if ($customer->contact_person)<div>{{ $customer->name }}</div>@endif
                    </td>
                </tr></table>
            </td></tr></table>
        </div>
    @endforeach
@endsection
