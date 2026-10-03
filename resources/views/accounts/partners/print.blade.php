@extends('layouts.accounts-print')

{{-- One partner's account statement: credits per finalized month, payments, running balance. --}}

@use('App\Support\Dec')
@php
    $L = fn ($v) => Dec::lakh($v ?? 0);
@endphp

@section('title', 'Statement ' . $partner->name)
@section('back', route('accounts.partners.show', $partner))
@section('heading', 'Partner Account Statement — ' . $partner->name)

@section('styles')
    .meta { display: flex; justify-content: space-between; margin-bottom: .8rem; font-size: 13px; }
    table.st td.l { text-align: left; }
    .totals { width: 45%; margin: 1rem 0 0 auto; }
@endsection

@section('content')
    <div class="meta">
        <div><strong>{{ $partner->name }}</strong> · {{ $partner->roleLabel() }}@if ($partner->phone) · {{ $partner->phone }}@endif</div>
        <div>Printed {{ now()->format('d/m/Y') }}</div>
    </div>

    <table class="st">
        <thead>
            <tr><th style="width:6.5rem">Date</th><th>Entry</th><th class="num">Credit</th><th class="num">Paid</th><th class="num">Balance</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $e)
                @php $credit = ! Dec::isNegative($e->amount); @endphp
                <tr>
                    <td>{{ $e->entry_date->format('d/m/Y') }}</td>
                    <td class="l">{{ $e->typeLabel() }}{{ $e->sheet ? ' — ' . $e->sheet->month->format('M Y') : '' }}{{ $e->method ? ' · ' . $e->method : '' }}@if ($e->note)<br><span class="muted" style="font-size:12px">{{ $e->note }}</span>@endif</td>
                    <td class="num">{{ $credit ? $L($e->amount) : '' }}</td>
                    <td class="num">{{ $credit ? '' : $L(ltrim($e->amount, '-')) }}</td>
                    <td class="num">{{ $L($e->running) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="l">Total credited</td><td class="num">{{ $L($earned) }}</td></tr>
        <tr><td class="l">Total paid</td><td class="num">{{ $L($paid) }}</td></tr>
        <tr class="total"><td class="l">{{ Dec::isNegative($balance) ? 'Paid in advance' : 'Balance to pay' }}</td><td class="num">{{ $L($balance) }}</td></tr>
    </table>

    <div class="sign"><div>Prepared By</div><div>{{ $partner->name }}</div><div>Approved By</div></div>
@endsection
