@extends('layouts.accounts-print')

@php $tk = fn ($v) => number_format((float) $v, 2) . '৳'; @endphp

@section('title', ($category?->name ?? 'হিসাব') . ' ' . $month->format('m/Y'))
@section('back', route('accounts.reports.ledger', ['month' => $month->format('Y-m'), 'category' => $category?->id]))
@section('heading', \App\Support\Bangla::month($month) . ' মাসের ' . $category?->name . ' খরচের হিসাব ' . $month->year)

@section('content')
<table>
    <thead>
        <tr><th class="c" style="width:3rem">নং</th><th style="width:7rem">তারিখ</th><th>বিবরণ</th><th class="num" style="width:9rem">{{ $category?->name }}</th><th class="num" style="width:9rem">মোট</th></tr>
    </thead>
    <tbody>
        @php $running = 0; @endphp
        @foreach ($days as $d)
            @php $running += $d['amount']; @endphp
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $d['date']->format('d-m-Y') }}</td>
                <td>{{ $d['description'] }}</td>
                <td class="num">{{ $d['amount'] ? $tk($d['amount']) : '' }}</td>
                <td class="num">{{ $d['amount'] ? $tk($d['amount']) : '-' }}</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="3">মোট খরচ</td><td class="num">{{ $tk($total) }}</td><td class="num">{{ $tk($total) }}</td></tr>
    </tbody>
</table>
<div class="sign"><div>প্রস্তুতকারী</div><div>হিসাবরক্ষক</div><div>অনুমোদনকারী</div></div>
@endsection
