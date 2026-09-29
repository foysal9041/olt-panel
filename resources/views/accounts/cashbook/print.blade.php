@extends('layouts.accounts-print')

@php
    $tk = fn ($v) => number_format((float) $v, 2) . '৳';
    $rows = max($income->count(), $expense->count(), 10);
@endphp

@section('title', 'প্রতিদিনের হিসাব ' . $date->format('d/m/Y'))
@section('back', route('accounts.cashbook.index', ['date' => $date->toDateString()]))
@section('heading', \App\Support\Bangla::month($date) . ' মাসের প্রতিদিনের হিসাব — ' . \App\Support\Bangla::day($date) . ' — ' . $date->format('d/m/Y'))

@section('content')
<table>
    <thead>
        <tr><th colspan="3" class="c">জমা</th><th colspan="2" class="c">খরচ</th></tr>
        <tr><th class="c" style="width:3rem">নং</th><th>আয়ের উৎস</th><th class="num">আয়ের পরিমান</th><th>ব্যায়ের খাত</th><th class="num">ব্যায়ের পরিমান</th></tr>
    </thead>
    <tbody>
        @for ($i = 0; $i < $rows; $i++)
            @php $in = $income[$i] ?? null; $ex = $expense[$i] ?? null; @endphp
            <tr>
                <td class="c muted">{{ $i + 1 }}</td>
                <td>{{ $in ? trim($in->category->name . ($in->description ? ' — ' . $in->description : '')) : '' }}</td>
                <td class="num">{{ $in ? $tk($in->amount) : '' }}</td>
                <td>{{ $ex ? ($ex->description ?: $ex->category->name) : '' }}</td>
                <td class="num">{{ $ex ? $tk($ex->amount) : '' }}</td>
            </tr>
        @endfor
        <tr class="total">
            <td colspan="2">মোট আয়</td><td class="num">{{ $tk($totals['day_income']) }}</td>
            <td>মোট ব্যায়</td><td class="num">{{ $tk($totals['total_expense']) }}</td>
        </tr>
    </tbody>
</table>

<table style="width: 50%; margin-top: 1.25rem">
    <tr><td>জের আয়{{ $countToday ? ' (হাতে নগদ গণনা)' : '' }}</td><td class="num">{{ $tk($totals['opening']) }}</td></tr>
    <tr><td>দিনের আয়</td><td class="num">{{ $tk($totals['day_income']) }}</td></tr>
    <tr><td>মোট আয়</td><td class="num">{{ $tk($totals['total_income']) }}</td></tr>
    <tr><td>মোট ব্যয়</td><td class="num">{{ $tk($totals['total_expense']) }}</td></tr>
    <tr class="total"><td>অবশিষ্ট টাকা</td><td class="num">{{ $tk($totals['closing']) }}</td></tr>
</table>

<div class="sign"><div>প্রস্তুতকারী</div><div>হিসাবরক্ষক</div><div>অনুমোদনকারী</div></div>
@endsection
