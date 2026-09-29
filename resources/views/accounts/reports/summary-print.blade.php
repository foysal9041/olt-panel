@extends('layouts.accounts-print')

@php
    $tk = fn ($v) => ($v < 0 ? '(' . number_format(abs($v), 2) . ')' : number_format((float) $v, 2)) . '৳';
    $net = $totalIncome - $totalExpense;
@endphp

@section('title', 'মোট খরচ ও ইনকাম ' . $month->format('m/Y'))
@section('back', route('accounts.reports.summary', ['month' => $month->format('Y-m')]))
@section('heading', \App\Support\Bangla::month($month) . ' মাসের মোট খরচ এবং ইনকাম ' . $month->year)

@section('content')
<table>
    <thead>
        <tr><th class="c" style="width:3rem">নং</th><th>তারিখ</th><th class="num">মোট ইনকাম</th><th class="num">মোট খরচ</th><th class="num">মোট জমা</th></tr>
    </thead>
    <tbody>
        @foreach ($days as $d)
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $d['date']->format('d-m-Y') }}</td>
                <td class="num">{{ $d['income'] ? $tk($d['income']) : '' }}</td>
                <td class="num">{{ $d['expense'] ? $tk($d['expense']) : '' }}</td>
                <td class="num {{ $d['net'] < 0 ? 'neg' : '' }}">{{ $d['income'] || $d['expense'] ? $tk($d['net']) : '-' }}</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="2">মোট</td><td class="num">{{ $tk($totalIncome) }}</td><td class="num">{{ $tk($totalExpense) }}</td><td class="num">{{ $tk($net) }}</td></tr>
    </tbody>
</table>

<table style="width:60%; margin-top:1.25rem">
    <thead><tr><th>খাত</th><th class="num">মোট</th></tr></thead>
    <tbody>
        @foreach ($byCategory as $c)
            <tr><td>{{ $c->name }} ({{ $c->type === 'income' ? 'আয়' : 'খরচ' }})</td><td class="num">{{ $tk($c->total) }}</td></tr>
        @endforeach
        <tr class="sub"><td>মাসের শুরুতে জের</td><td class="num">{{ $tk($opening) }}</td></tr>
        <tr class="total"><td>মাস শেষে অবশিষ্ট</td><td class="num">{{ $tk($opening + $net) }}</td></tr>
    </tbody>
</table>
<div class="sign"><div>প্রস্তুতকারী</div><div>হিসাবরক্ষক</div><div>অনুমোদনকারী</div></div>
@endsection
