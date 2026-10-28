@extends('pdf.layout')

{{-- PDF of accounts/settlements/invoice (zone settlement customer invoice) for email — same figures and wording. --}}

@use('App\Support\Dec')
@use('App\Support\NumberWords')
@php $n = fn ($v) => number_format((float) Dec::round($v, 2), 2); @endphp

@section('styles')
    .bill-period { margin: 0 0 4mm; text-align: center; font-size: 10.5pt; color: #334155; }
    .bill-for { font-size: 15pt; margin-bottom: 2.5mm; }
    table.bill { width: 100%; border-collapse: collapse; }
    table.bill th, table.bill td { border: 1px solid #000; padding: 1.6mm 3mm; text-align: center; font-size: 11.5pt; }
    table.bill th { font-weight: bold; background: #e9f0f9; color: #0b3f86; }
    table.bill .sn { width: 14mm; font-weight: bold; }
    table.bill .part { width: 62mm; font-weight: bold; }
    table.bill tr.payable td { font-weight: bold; background: #e9f0f9; }
    .words { margin-top: 9mm; }
@endsection

@section('content')
    @foreach ($rows as $row)
        @php $c = $row->calc($settlement->bkash_percent); @endphp
        <div @class(['pad', 'last' => $loop->last])>
            @include('pdf.partials.pad')
            <table class="sheet"><tr><td class="sheet-body">
                <table class="top">
                    <tr>
                        <td class="meta">
                            <div>Invoice No: {{ $row->invoice_no }}</div>
                            <div>Date: {{ $settlement->invoice_date->format('d/m/Y') }}</div>
                            <div>To: <span style="font-weight:normal">{{ $row->name }}</span></div>
                        </td>
                        <td class="tag">INVOICE</td>
                    </tr>
                </table>

                <div class="bill-for">Bill for {{ $settlement->month->format('M Y') }}</div>
                <div class="bill-period">Billing period: {{ $settlement->periodLabel('d/m/Y') }}</div>

                <table class="bill">
                    <thead><tr><th class="sn">SN</th><th>Particular</th><th>Amount</th></tr></thead>
                    <tbody>
                        <tr><td class="sn">1</td><td class="part">Total Payment</td><td>{{ $n($c['payment']) }}</td></tr>
                        <tr><td class="sn">2</td><td class="part">Net Bill</td><td>{{ $n($c['difference']) }}</td></tr>
                        <tr class="payable"><td class="sn"></td><td class="part">Total Payable</td><td>{{ $n($c['invoice']) }}</td></tr>
                    </tbody>
                </table>

                <p class="words">In word: <span style="font-weight:normal">{{ NumberWords::international($c['invoice']) }}</span></p>
            </td></tr>
            <tr><td class="sheet-signs">
                <table class="signs"><tr>
                    <td>
                        <p class="role">Prepared By</p>
                        <div class="line"></div>
                        <div>{{ $settlement->prepared_by }}</div>
                        @if ($settlement->prepared_title)<div>{{ $settlement->prepared_title }}</div>@endif
                        <div>{{ \App\Support\Branding::legalName() }}</div>
                    </td>
                    <td class="gap"></td>
                    <td>
                        <p class="role">Received By</p>
                        <div class="line"></div>
                        @if (filled($row->customer?->name))<div>{{ $row->customer->name }}</div>@endif
                    </td>
                </tr></table>
            </td></tr></table>
        </div>
    @endforeach
@endsection
