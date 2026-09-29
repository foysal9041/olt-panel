@extends('adminlte::page')

@section('title', 'Monthly Summary')

@php
    $tk = fn ($v) => ($v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 2);
    $net = $totalIncome - $totalExpense;
@endphp

@section('content_header')
<x-accounts.header title="মাসিক সারাংশ" icon="fas fa-calendar-alt"
    subtitle="{{ \App\Support\Bangla::month($month) }} {{ $month->year }} — মোট খরচ ও ইনকাম">
    <a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print</a>
</x-accounts.header>
@stop

@section('content')

@include('accounts.reports._nav', ['active' => 'summary'])

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#64748b"><div class="acct-stat-label">জের (মাসের শুরু)</div><div class="acct-stat-value">{{ $tk($opening) }}</div></div>
    <div class="acct-stat" style="--accent:#16a34a"><div class="acct-stat-label">মোট ইনকাম</div><div class="acct-stat-value text-success">{{ $tk($totalIncome) }}</div></div>
    <div class="acct-stat" style="--accent:#e11d48"><div class="acct-stat-label">মোট খরচ</div><div class="acct-stat-value text-danger">{{ $tk($totalExpense) }}</div></div>
    <div class="acct-stat" style="--accent:#4f46e5"><div class="acct-stat-label">মাসের জমা (আয় − খরচ)</div><div class="acct-stat-value {{ $net < 0 ? 'text-danger' : '' }}">{{ $tk($net) }}</div></div>
    <div class="acct-stat" style="--accent:#0ea5e9"><div class="acct-stat-label">অবশিষ্ট (মাস শেষে)</div><div class="acct-stat-value">{{ $tk($opening + $net) }}</div></div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title">দিন-ভিত্তিক</h3></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th class="pl-3">নং</th><th>তারিখ</th><th class="text-right">মোট ইনকাম</th><th class="text-right">মোট খরচ</th><th class="text-right pr-3">মোট জমা</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($days as $d)
                            <tr class="{{ $d['income'] || $d['expense'] ? '' : 'text-muted' }}">
                                <td class="pl-3">{{ $loop->iteration }}</td>
                                <td>
                                    @can('access-accounts-cashbook')
                                        <a href="{{ route('accounts.cashbook.index', ['date' => $d['date']->toDateString()]) }}" class="text-reset">{{ $d['date']->format('d-m-Y') }}</a>
                                    @else
                                        {{ $d['date']->format('d-m-Y') }}
                                    @endcan
                                </td>
                                <td class="text-right text-success">{{ $d['income'] ? $tk($d['income']) : '' }}</td>
                                <td class="text-right text-danger">{{ $d['expense'] ? $tk($d['expense']) : '' }}</td>
                                <td class="text-right pr-3 font-weight-bold {{ $d['net'] < 0 ? 'text-danger' : '' }}">{{ $d['income'] || $d['expense'] ? $tk($d['net']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#1e3a8a;color:#fff;font-weight:700">
                            <td colspan="2" class="pl-3">মোট</td>
                            <td class="text-right">{{ $tk($totalIncome) }}</td>
                            <td class="text-right">{{ $tk($totalExpense) }}</td>
                            <td class="text-right pr-3">{{ $tk($net) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title">খাত-ভিত্তিক মোট</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse ($byCategory as $c)
                            <tr>
                                <td class="pl-3">
                                    @can('access-accounts-reports')
                                        <a href="{{ route('accounts.reports.ledger', ['month' => $month->format('Y-m'), 'category' => $c->id]) }}">{{ $c->name }}</a>
                                    @else {{ $c->name }} @endcan
                                    <small class="text-muted">· {{ $c->entries }} entries</small>
                                </td>
                                <td class="text-right pr-3 font-weight-bold {{ $c->type === 'income' ? 'text-success' : 'text-danger' }}">{{ $tk($c->total) }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4">No entries this month</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop
