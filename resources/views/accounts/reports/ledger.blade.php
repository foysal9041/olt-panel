@extends('adminlte::page')

@section('title', 'Monthly Ledger')

@php
    $tk = fn ($v) => '৳' . number_format((float) $v, 2);
    $bnMonth = \App\Support\Bangla::month($month);
@endphp

@section('content_header')
<x-accounts.header title="মাসিক হিসাব" icon="fas fa-calendar-alt"
    subtitle="{{ $bnMonth }} {{ $month->year }} — {{ $category?->name }} ({{ $category?->type === 'income' ? 'আয়' : 'খরচ' }})">
    @if ($category)
        <a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print</a>
    @endif
</x-accounts.header>
@stop

@section('content')

@include('accounts.reports._nav', ['active' => 'ledger'])

<div class="d-flex flex-wrap mb-3" style="gap:.4rem">
    @foreach ($categories as $c)
        <a href="{{ route('accounts.reports.ledger', ['month' => $month->format('Y-m'), 'category' => $c->id]) }}"
           class="btn btn-sm {{ $category?->id === $c->id ? ($c->type === 'income' ? 'btn-success' : 'btn-danger') : 'btn-outline-secondary' }}">
            {{ $c->name }}
        </a>
    @endforeach
</div>

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title">{{ $bnMonth }} মাসের {{ $category?->name }} হিসাব {{ $month->year }}</h3>
        <strong class="{{ $category?->type === 'income' ? 'text-success' : 'text-danger' }}">মোট {{ $tk($total) }}</strong>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th class="pl-3" style="width:3rem">নং</th>
                    <th style="width:8rem">তারিখ</th>
                    <th>বিবরণ</th>
                    <th class="text-right pr-3" style="width:10rem">{{ $category?->name }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $d)
                    <tr class="{{ $d['amount'] ? '' : 'text-muted' }}">
                        <td class="pl-3">{{ $loop->iteration }}</td>
                        <td>
                            @can('access-accounts-cashbook')
                                <a href="{{ route('accounts.cashbook.index', ['date' => $d['date']->toDateString()]) }}" class="text-reset">{{ $d['date']->format('d-m-Y') }}</a>
                            @else
                                {{ $d['date']->format('d-m-Y') }}
                            @endcan
                        </td>
                        <td>{{ $d['description'] }}</td>
                        <td class="text-right pr-3 {{ $d['amount'] ? 'font-weight-bold' : '' }}">{{ $d['amount'] ? $tk($d['amount']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#1e3a8a;color:#fff;font-weight:700">
                    <td colspan="3" class="pl-3">মোট খরচ</td>
                    <td class="text-right pr-3">{{ $tk($total) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@stop
