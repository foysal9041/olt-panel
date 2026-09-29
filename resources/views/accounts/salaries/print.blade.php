@extends('layouts.accounts-print')

@php
    $n = fn ($v) => (float) $v ? number_format((float) $v) : '';
    $items = collect($rows)->map(function ($r) {
        $r->net = round($r->salary + $r->house_rent + $r->bonus - $r->advance - $r->deduction, 2);
        return $r;
    });
@endphp

@section('title', 'বেতন ' . $month->format('m/Y'))
@section('back', route('accounts.salaries.index', ['month' => $month->format('Y-m')]))
@section('heading', \App\Support\Bangla::month($month) . ' মাসের বেতন খরচের হিসাব ' . $month->year . ' — পরিশোধ: ' . \App\Support\Bangla::month($payMonth) . ' ' . $payMonth->year)
@section('styles') .sheet { max-width: 1100px; } td, th { font-size: 12.5px; } @endsection

@section('content')
@unless ($sheet?->isPosted())
    <p class="muted" style="text-align:center;margin:-.3rem 0 .6rem">{{ $sheet ? 'খসড়া — এখনো পোস্ট করা হয়নি (Draft, not posted)' : 'খসড়া — সংরক্ষণ করা হয়নি (Draft, not saved)' }}</p>
@endunless
<table>
    <thead>
        <tr><th class="c">SL</th><th>Emp ID</th><th>Name</th><th>Designation</th><th class="num">Salary</th><th class="num">House Rent</th><th class="num">Eid Bonus</th><th class="num">Advance</th><th class="num">Deduction</th><th class="num">Net Pay</th><th style="width:7rem">Signature</th></tr>
    </thead>
    <tbody>
        @foreach ($items as $it)
            <tr>
                <td class="c">{{ $loop->iteration }}</td><td>{{ $it->emp_code }}</td><td>{{ $it->name }}</td><td>{{ $it->designation }}</td>
                <td class="num">{{ $n($it->salary) }}</td><td class="num">{{ $n($it->house_rent) }}</td><td class="num">{{ $n($it->bonus) }}</td>
                <td class="num">{{ $n($it->advance) }}</td><td class="num">{{ $n($it->deduction) }}</td><td class="num"><b>{{ number_format($it->net) }}</b></td><td></td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="4">Total</td>
            @foreach (['salary', 'house_rent', 'bonus', 'advance', 'deduction', 'net'] as $f)
                <td class="num">{{ number_format($items->sum($f)) }}</td>
            @endforeach
            <td></td>
        </tr>
    </tbody>
</table>
<div class="sign"><div>প্রস্তুতকারী</div><div>হিসাবরক্ষক</div><div>অনুমোদনকারী</div></div>
@endsection
