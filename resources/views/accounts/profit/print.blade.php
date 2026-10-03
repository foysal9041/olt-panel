@extends('layouts.accounts-print')

{{-- The month's net profit statement and the shareholders' sheet to sign, laid out like the office's Excel. --}}

@use('App\Models\ProfitSheet')
@use('App\Support\Dec')
@php
    $L = fn ($v) => Dec::lakh($v);
    $lines = collect($sheet->lines ?? []);
    $c = $calc;
    $num = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
@endphp

@section('title', 'Net Profit ' . $sheet->month->format('F Y'))
@section('back', route('accounts.profit.index', ['month' => $sheet->month->format('Y-m')]))
@section('heading', 'Net Profit Statement — ' . $sheet->month->format('F Y') . ($sheet->isFinal() ? '' : ' (DRAFT)'))

@section('styles')
    .sheet { max-width: 820px; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    table.pl { width: 100%; border-collapse: collapse; margin-bottom: .9rem; }
    table.pl td { border: 1px solid #94a3b8; padding: .22rem .6rem; text-align: center; font-size: 13px; color: #0f172a; }
    table.pl td.n { text-align: right; width: 34%; font-variant-numeric: tabular-nums; white-space: nowrap; }
    tr.in td { background: #d9f2df; }
    tr.cost td { background: #fde9c4; }
    tr.com td { background: #e3f1cc; }
    tr.tot td { font-weight: 700; }
    tr.in.tot td { background: #a7e0b5; }
    tr.cost.tot td { background: #fbd38d; }
    tr.key td { background: #bae6fd; font-weight: 700; font-size: 14px; }
    .two { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
    h3.sec { margin: 1rem 0 .4rem; font-size: 14px; text-align: left; color: #1e3a8a; }
    .page-break { page-break-before: always; break-before: page; }
    table.dist th, table.dist td { font-size: 13px; padding: .35rem .5rem; }
    table.dist td.sig { width: 22%; }
    .note { margin-top: .6rem; font-size: 12px; color: #475569; }
    .draft-mark { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 120px; font-weight: 800; color: rgba(220, 38, 38, .08); transform: rotate(-24deg); pointer-events: none; }
    .final-line { margin-top: .4rem; font-size: 12px; color: #475569; text-align: right; }
@endsection

@section('content')
    @unless ($sheet->isFinal())<div class="draft-mark">DRAFT</div>@endunless
    {{-- ============ Statement ============ --}}
    <table class="pl">
        @foreach (['bill' => 'in', 'other_income' => 'in', 'fixed_cost' => 'cost', 'other_cost' => 'cost'] as $key => $class)
            @foreach ($lines->where('section', $key) as $line)
                <tr class="{{ $class }}"><td>{{ $line['label'] }}</td><td class="n">{{ $L($line['amount']) }}</td></tr>
            @endforeach
            @if ($key === 'other_cost')
                <tr class="com"><td>Commision</td><td class="n">{{ $L($c['commission_total']) }}</td></tr>
            @endif
            <tr class="{{ $class }} tot {{ $key === 'bill' ? 'key' : '' }}">
                <td>{{ ['bill' => 'Total Bill Income (Cash IN)', 'other_income' => 'Total (Others) Income', 'fixed_cost' => 'Total Fixed Cost', 'other_cost' => 'Total (Others) Cost'][$key] }}</td>
                <td class="n">{{ $L($key === 'other_cost' ? $c['other_cost'] : $c['sections'][$key]) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="two">
        <div>
            <table class="pl">
                <tr class="in"><td>Total Income (Commissionable)</td><td class="n">{{ $L($c['sections']['bill']) }}</td></tr>
                <tr class="in"><td>Others Income</td><td class="n">{{ $L($c['sections']['other_income']) }}</td></tr>
                <tr class="in tot"><td>Total Income</td><td class="n">{{ $L($c['income']) }}</td></tr>
            </table>
            <table class="pl">
                <tr class="cost"><td>Total Fixed Cost</td><td class="n">{{ $L($c['sections']['fixed_cost']) }}</td></tr>
                <tr class="cost"><td>Total (Others) Cost</td><td class="n">{{ $L($c['other_cost']) }}</td></tr>
                <tr class="cost tot"><td>Total Cost</td><td class="n">{{ $L($c['cost']) }}</td></tr>
            </table>
        </div>
        <div>
            <table class="pl">
                <tr class="in"><td>Total Income</td><td class="n">{{ $L($c['income']) }}</td></tr>
                <tr class="cost"><td>Total Cost</td><td class="n">{{ $L($c['cost']) }}</td></tr>
                <tr class="key"><td>Net Profit</td><td class="n">{{ $L($c['net']) }}</td></tr>
            </table>
            <table class="pl">
                <tr class="com tot"><td>Income (Commissionable)</td><td class="n">{{ $L($c['commission_base']) }}</td></tr>
                <tr class="com"><td>Commission</td><td class="n">{{ $L($c['commission_total']) }}</td></tr>
                @foreach ($c['commission'] as $h)
                    <tr class="com"><td>{{ $h['name'] }} ({{ $num($h['percent']) }}%)</td><td class="n">{{ $L($h['amount']) }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>
    @if ($sheet->notes)<p class="note">{{ $sheet->notes }}</p>@endif
    @if ($sheet->isFinal())<p class="final-line">Finalized by {{ $sheet->finalizer?->name ?? '—' }} on {{ $sheet->finalized_at->format('d/m/Y h:i A') }}</p>@endif

    {{-- ============ Shareholders ============ --}}
    <div class="page-break"></div>
    <h3 class="sec">Net Profit Distribution — {{ $sheet->month->format('F Y') }}</h3>
    <table class="dist">
        <thead>
            <tr><th colspan="2">Net Profit</th><th class="num">{{ $L($c['net']) }}</th><th>Per Share</th><th class="num">{{ $L($c['per_share']) }}</th><th></th></tr>
            <tr><th class="c" style="width:2.5rem">SN</th><th>Name</th><th class="c">Share</th><th class="num">Per Share</th><th class="num">Per person BDT</th><th>Signature</th></tr>
        </thead>
        <tbody>
            @foreach ($c['people'] as $k => $p)
                <tr>
                    <td class="c">{{ $k + 1 }}</td>
                    <td style="text-align:left">{{ $p['name'] }}</td>
                    <td class="c">{{ $num($p['share']) }}</td>
                    <td class="num">{{ $L($c['per_share']) }}</td>
                    <td class="num">{{ $L($p['amount']) }}</td>
                    <td class="sig"></td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="c">{{ $num($c['shares_total']) }}</td>
                <td></td>
                <td class="num">{{ $L($c['net']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="sign"><div>Prepared By</div><div>Checked By</div><div>Approved By</div></div>
@endsection
