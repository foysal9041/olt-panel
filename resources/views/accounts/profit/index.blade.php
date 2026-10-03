@extends('adminlte::page')

@section('title', 'Net Profit & Shares')

@use('App\Models\ProfitSheet')
@use('App\Models\TransactionCategory')
@use('App\Models\ZoneSettlement')
@use('App\Support\Dec')
@php
    $L = fn ($v) => Dec::lakh($v);
    $num = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
    $ym = $month->format('Y-m');
    $final = $sheet->isFinal();
    $isAdmin = auth()->user()->isAdmin();
    $tones = [
        'bill' => ['#15803d', 'fas fa-file-invoice-dollar'],
        'other_income' => ['#0f766e', 'fas fa-plus-circle'],
        'fixed_cost' => ['#b45309', 'fas fa-building'],
        'other_cost' => ['#c2410c', 'fas fa-shopping-cart'],
    ];
    $placeholders = [
        'bill' => 'e.g. Previous Month Bill — Bashar',
        'other_income' => 'e.g. Product Sell',
        'fixed_cost' => 'e.g. Office rent',
        'other_cost' => 'e.g. Asset Purchase',
    ];
    $hint = function (?string $source) use ($month) {
        [$kind, $id] = array_pad(explode(':', (string) $source, 2), 2, null);

        return match ($kind) {
            'settlement' => isset(ZoneSettlement::CYCLES[$id])
                ? ['fas fa-file-excel', 'Zone Settlement · ' . ZoneSettlement::CYCLES[$id]['label'] . ' · ' . (new ZoneSettlement(['month' => $month, 'cycle' => $id]))->periodLabel('d M')]
                : null,
            'bw' => ['fas fa-tachometer-alt', 'Bandwidth Billing · paid in ' . $month->format('F') . ' toward this month\'s bill'],
            'bwprev' => ['fas fa-tachometer-alt', 'Bandwidth Billing · paid in ' . $month->format('F') . ' toward earlier dues'],
            'customer' => ['fas fa-tachometer-alt', 'Bandwidth client'],
            'category' => ['fas fa-book-open', 'Cash Book head · ' . $month->format('F')],
            default => ['fas fa-pen', 'Added by hand'],
        };
    };
    $i = 0;
@endphp

@section('content_header')
<x-accounts.header title="Net Profit & Shares" icon="fas fa-chart-line"
    subtitle="Monthly profit & share distribution — {{ $month->format('F Y') }}">
    @unless ($final)
        <a href="{{ route('accounts.profit.index', ['month' => $ym, 'fresh' => 1]) }}" class="btn btn-outline-secondary btn-sm"
           @if ($saved) onclick="return confirm('Fill the lines in again from Zone Settlement and the Cash Book? Your saved draft stays until you Save.')" @endif>
            <i class="fas fa-sync-alt"></i> Reload from data
        </a>
    @endunless
    @if ($saved)
        <a href="{{ route('accounts.profit.print', ['month' => $ym]) }}" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print</a>
    @endif
</x-accounts.header>
@stop

@section('css')
<style>
    .pf-bar { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .pf-bar .input-group { width: auto; }
    .pf-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .7rem; border-radius: 999px; font-size: .78rem; font-weight: 600; }
    .pf-chip.final { background: #dcfce7; color: #15803d; }
    .pf-chip.draft { background: #fef3c7; color: #b45309; }
    .pf-months a { display: inline-flex; align-items: center; gap: .25rem; padding: .2rem .6rem; margin: 0 .2rem .2rem 0; border-radius: 999px; background: #fff; border: 1px solid #e2e8f0; font-size: .78rem; font-weight: 600; color: #475569; }
    .pf-months a.active { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .pf-sec .card-header { border-left: 4px solid var(--tone); }
    .pf-sec .card-title i { color: var(--tone); }
    .pf-sec-total { font-weight: 800; color: var(--tone); font-variant-numeric: tabular-nums; }
    .pf-table { margin: 0; }
    .pf-table td, .pf-table th { vertical-align: middle; border-top: 1px solid #f1f5f9; padding: .4rem .6rem; }
    .pf-table th { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-top: 0; }
    .pf-table .form-control { height: calc(1.6em + .5rem + 2px); padding: .25rem .5rem; font-size: .88rem; }
    .pf-table .amt { width: 11rem; }
    .pf-table .amt input { text-align: right; font-variant-numeric: tabular-nums; }
    .pf-table .act { width: 2.2rem; text-align: center; }
    .pf-hint { margin-top: .15rem; font-size: .72rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pf-fixed td { background: #fff7ed; font-weight: 600; }
    .pf-del { color: #cbd5e1; }
    .pf-del:hover { color: #dc2626; }
    .pf-add { font-size: .82rem; font-weight: 600; }
    .pf-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .pf-sum { position: sticky; top: 4.5rem; }
    .pf-sum .acct-list-row { padding: .55rem 1.1rem; }
    .pf-sum .lbl { flex: 1; font-size: .86rem; color: #475569; }
    .pf-sum .val { font-weight: 700; font-variant-numeric: tabular-nums; }
    .pf-sum .grand { background: #f8fafc; }
    .pf-sum .grand .lbl { font-weight: 700; color: #0f172a; }
    .pf-warn { font-size: .8rem; color: #b45309; }
</style>
@stop

@section('content')

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

{{-- ============ Month + status ============ --}}
<div class="pf-bar">
    <form method="GET" class="input-group input-group-sm">
        <div class="input-group-prepend"><a href="{{ route('accounts.profit.index', ['month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-light border" title="Previous month"><i class="fas fa-chevron-left"></i></a></div>
        <input type="month" name="month" value="{{ $ym }}" class="form-control" onchange="this.form.submit()" style="width: 11rem">
        <div class="input-group-append"><a href="{{ route('accounts.profit.index', ['month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-light border" title="Next month"><i class="fas fa-chevron-right"></i></a></div>
    </form>
    @if ($final)
        <span class="pf-chip final"><i class="fas fa-lock"></i> Finalized · {{ $sheet->finalizer?->name ?? '—' }}, {{ $sheet->finalized_at->format('d M Y, h:i A') }}</span>
    @elseif ($saved && ! $fresh)
        <span class="pf-chip draft"><i class="fas fa-pen"></i> Draft · saved by {{ $saved->editor?->name ?? $saved->creator?->name ?? '—' }}, {{ $saved->updated_at->format('d M, h:i A') }}</span>
    @elseif ($saved)
        <span class="pf-warn"><i class="fas fa-sync-alt"></i> Lines filled in again from the data — not saved. <a href="{{ route('accounts.profit.index', ['month' => $ym]) }}">Cancel</a></span>
    @else
        <span class="pf-warn"><i class="fas fa-magic"></i> New draft — lines filled in from Zone Settlement and the Cash Book. Check them, then Save.</span>
    @endif
    @if ($months->isNotEmpty())
        <div class="pf-months ml-auto">
            @foreach ($months->take(8) as $m)
                <a href="{{ route('accounts.profit.index', ['month' => $m->month->format('Y-m')]) }}" @class(['active' => $m->month->isSameMonth($month)])>
                    @if ($m->status === 'final')<i class="fas fa-lock" style="font-size:.65rem"></i>@endif {{ $m->month->format('M Y') }}
                </a>
            @endforeach
        </div>
    @endif
</div>

@if ($final)
    <div class="alert alert-light border d-flex align-items-start" style="gap:.75rem">
        <i class="fas fa-lock fa-lg text-success mt-1"></i>
        <div class="flex-grow-1 small">
            <strong>{{ $month->format('F Y') }} is closed.</strong>
            The figures below are frozen; each partner's share and commission are in their account. The month's Cash Book, Income &amp; Expenses
            and Zone Settlement can't be changed until an admin reopens it.
            @if ($drift)
                <div class="mt-2 text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>The data has changed since it was finalized:</strong>
                    @foreach ($drift as $d)
                        <div>{{ \App\Support\Ui::t($d['label']) }}: was ৳{{ $L($d['was']) }}, now ৳{{ $L($d['now']) }}</div>
                    @endforeach
                    Reopen the month, reload and finalize again if the new figures are right.
                </div>
            @endif
        </div>
        @if ($isAdmin)
            <form method="POST" action="{{ route('accounts.profit.reopen', $sheet) }}" class="js-confirm-delete"
                  data-confirm-message="Reopen {{ $month->format('F Y') }}? The partners' credits for it will be taken back (payments stay) and the month's books open again.">
                @csrf
                <button class="btn btn-sm btn-outline-danger text-nowrap"><i class="fas fa-lock-open"></i> Reopen month</button>
            </form>
        @endif
    </div>
@endif

<div class="row">

    {{-- ============ Lines ============ --}}
    <div class="col-xl-8">
        <form method="POST" action="{{ route('accounts.profit.save') }}" id="pf-form">
            @csrf
            <input type="hidden" name="month" value="{{ $ym }}">
            <fieldset @disabled($final)>

            @foreach (ProfitSheet::SECTIONS as $key => $title)
                @php [$tone, $icon] = $tones[$key]; $rows = collect($sheet->lines ?? [])->where('section', $key); @endphp
                <div class="card acct-panel pf-sec" style="--tone: {{ $tone }}">
                    <div class="card-header">
                        <h3 class="card-title"><i class="{{ $icon }} mr-1"></i> {{ $title }}
                            @if ($key === 'bill') <small class="text-muted">commissionable</small> @endif
                        </h3>
                        <span class="pf-sec-total" data-total="{{ $key }}">{{ $L($key === 'other_cost' ? $calc['other_cost'] : $calc['sections'][$key]) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table pf-table">
                            <tbody data-section="{{ $key }}">
                                @foreach ($rows as $line)
                                    @php $h = $hint($line['source'] ?? null); $n = $i++; @endphp
                                    <tr>
                                        <td>
                                            <input type="hidden" name="lines[{{ $n }}][section]" value="{{ $key }}">
                                            <input type="hidden" name="lines[{{ $n }}][source]" value="{{ $line['source'] ?? '' }}">
                                            @if ($final)
                                                <div class="font-weight-bold">{{ \App\Support\Ui::t($line['label']) }}</div>
                                            @else
                                                <input type="text" name="lines[{{ $n }}][label]" value="{{ $line['label'] }}" class="form-control" maxlength="120" required>
                                            @endif
                                            @if ($h)<div class="pf-hint"><i class="{{ $h[0] }}"></i> {{ $h[1] }}</div>@endif
                                        </td>
                                        <td class="amt">
                                            @if ($final)
                                                <div class="pf-num font-weight-bold">{{ $L($line['amount']) }}</div>
                                            @else
                                                <input type="number" name="lines[{{ $n }}][amount]" value="{{ $line['amount'] }}" step="0.01" min="0" class="form-control js-amt">
                                            @endif
                                        </td>
                                        <td class="act">@unless ($final)<button type="button" class="btn btn-link btn-sm p-0 pf-del js-del" title="Remove"><i class="fas fa-times"></i></button>@endunless</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($key === 'other_cost')
                                <tbody>
                                    <tr class="pf-fixed">
                                        <td>Commission <span class="text-muted small font-weight-normal">— worked out below</span></td>
                                        <td class="amt pf-num pr-3" data-commission-total>{{ $L($calc['commission_total']) }}</td>
                                        <td class="act"><i class="fas fa-lock text-muted small"></i></td>
                                    </tr>
                                </tbody>
                            @endif
                        </table>
                    </div>
                    @unless ($final)
                        <div class="card-footer py-2">
                            <button type="button" class="btn btn-link btn-sm p-0 pf-add js-add" data-section="{{ $key }}" data-placeholder="{{ $placeholders[$key] }}" style="color: {{ $tone }}">
                                <i class="fas fa-plus"></i> Add line
                            </button>
                        </div>
                    @endunless
                </div>
            @endforeach

            {{-- Commission --}}
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-percentage mr-1 text-primary"></i> Commission</h3>
                    <span class="small text-muted">on Income (Commissionable) <b class="pf-num" data-commission-base>{{ $L($calc['commission_base']) }}</b> = Bill income − Fixed cost</span>
                </div>
                <div class="card-body p-0">
                    <table class="table pf-table">
                        <tbody>
                            @forelse ($calc['commission'] as $c)
                                <tr data-percent="{{ $c['percent'] }}">
                                    <td>@if ($c['partner_id'])<a href="{{ Gate::allows('access-accounts-partners') ? route('accounts.partners.show', $c['partner_id']) : '#' }}" style="color:#0f172a">{{ $c['name'] }}</a>@else {{ $c['name'] }} @endif</td>
                                    <td class="pf-num text-muted" style="width:6rem">{{ $num($c['percent']) }}%</td>
                                    <td class="amt pf-num font-weight-bold pr-3" data-amount>{{ $L($c['amount']) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-muted small">No commission holders in Partners.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Shareholders --}}
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users mr-1 text-primary"></i> Shareholders</h3>
                    <span class="small text-muted">Per share ৳<b class="pf-num" data-per-share>{{ $L($calc['per_share']) }}</b> · {{ $num($calc['shares_total']) }} shares</span>
                </div>
                <div class="card-body p-0">
                    <table class="table pf-table">
                        <thead><tr><th style="width:2.5rem">SN</th><th>Name</th><th class="pf-num">Share</th><th class="pf-num pr-3">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($calc['people'] as $k => $p)
                                <tr data-share="{{ $p['share'] }}">
                                    <td class="text-muted small">{{ $k + 1 }}</td>
                                    <td>@if ($p['partner_id'])<a href="{{ Gate::allows('access-accounts-partners') ? route('accounts.partners.show', $p['partner_id']) : '#' }}" style="color:#0f172a">{{ $p['name'] }}</a>@else {{ $p['name'] }} @endif</td>
                                    <td class="pf-num">{{ $num($p['share']) }}</td>
                                    <td class="amt pf-num font-weight-bold pr-3" data-amount>{{ $L($p['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer py-2 small d-flex justify-content-between align-items-center">
                    <span class="text-muted">
                        @if ($final) Frozen when the month was finalized. @else Taken from Partners as they are now; frozen when you finalize. @endif
                        @can('access-accounts-partners') <a href="{{ route('accounts.partners.index') }}">Partners <i class="fas fa-arrow-right"></i></a> @endcan
                    </span>
                    @if (abs((float) $calc['shares_total'] - 100) > 0.0001)
                        <span class="pf-warn"><i class="fas fa-exclamation-triangle"></i> Shares add up to {{ $num($calc['shares_total']) }}, not 100</span>
                    @endif
                </div>
            </div>

            <div class="card acct-panel">
                <div class="card-body">
                    <label class="small font-weight-bold">Note</label>
                    <textarea name="notes" rows="2" maxlength="1000" class="form-control" placeholder="optional">{{ old('notes', $sheet->notes) }}</textarea>
                    @unless ($final)
                        <div class="d-flex justify-content-end align-items-center flex-wrap mt-3" style="gap:.5rem">
                            @if ($saved && $isAdmin)
                                <button type="submit" form="pf-delete" class="btn btn-link text-danger mr-auto"><i class="fas fa-trash-alt"></i> Delete draft</button>
                            @endif
                            <button name="action" value="save" class="btn btn-outline-primary"><i class="fas fa-save"></i> Save draft</button>
                            @if ($isAdmin)
                                <button name="action" value="finalize" class="btn btn-success js-finalize"><i class="fas fa-lock"></i> Save &amp; finalize {{ $month->format('F') }}</button>
                            @endif
                        </div>
                        <div class="small text-muted text-right mt-2">
                            Finalizing freezes the sheet, credits each partner's account and closes {{ $month->format('F Y') }}'s books.
                            @unless ($isAdmin) Only an admin can finalize. @endunless
                        </div>
                    @endunless
                </div>
            </div>
            </fieldset>
        </form>
        @if ($saved && ! $final && $isAdmin)
            <form method="POST" action="{{ route('accounts.profit.destroy', $saved) }}" id="pf-delete" class="js-confirm-delete" data-confirm-message="Delete the {{ $month->format('F Y') }} draft?">
                @csrf @method('DELETE')
            </form>
        @endif
    </div>

    {{-- ============ Summary ============ --}}
    <div class="col-xl-4">
        <div class="pf-sum">
            <div @class(['acct-stat', 'acct-stat-hero', 'mb-3'])>
                <div class="acct-stat-label">Net Profit · {{ $month->format('M Y') }} <i class="fas {{ $final ? 'fa-lock' : 'fa-chart-line' }}"></i></div>
                <div class="acct-stat-value" data-net>৳{{ $L($calc['net']) }}</div>
                <div class="acct-stat-foot">Per share ৳<span data-per-share>{{ $L($calc['per_share']) }}</span> · {{ $final ? 'final' : 'draft' }}</div>
            </div>

            <div class="card acct-panel">
                <div class="card-body p-0">
                    @foreach ([
                        ['Total Bill Income (commissionable)', 'bill', false],
                        ['Others Income', 'other_income', false],
                        ['Total Income', 'income', true],
                        ['Total Fixed Cost', 'fixed_cost', false],
                        ['Total Others Cost (with commission)', 'other_cost', false],
                        ['Total Cost', 'cost', true],
                        ['Net Profit', 'net', true],
                    ] as [$label, $key, $grand])
                        @php $value = in_array($key, ['income', 'cost', 'net', 'other_cost']) ? $calc[$key] : $calc['sections'][$key]; @endphp
                        <div @class(['acct-list-row', 'grand' => $grand])>
                            <span class="lbl">{{ $label }}</span>
                            <span class="val {{ $key === 'net' ? 'text-income' : '' }}" data-sum="{{ $key }}">{{ $L($value) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-tags mr-1 text-warning"></i> Cash Book heads</h3>
                    <span class="small text-muted">where each counts</span>
                </div>
                <div class="card-body p-0">
                    @foreach ($heads as $head)
                        <div class="acct-list-row py-2">
                            <span class="acct-list-main small">
                                <i class="fas fa-arrow-{{ $head->type === 'income' ? 'down text-success' : 'up text-danger' }} mr-1" style="font-size:.7rem"></i>{{ $head->displayName() }}
                            </span>
                            <form method="POST" action="{{ route('accounts.profit.heads', $head) }}" class="btn-group btn-group-sm">
                                @csrf @method('PUT')
                                @foreach (TransactionCategory::PL_GROUPS[$head->type] ?? [] as $g => $gLabel)
                                    <button name="pl_group" value="{{ $g }}" title="{{ $gLabel }}" @class(['btn', 'btn-primary' => $head->plGroup() === $g, 'btn-light border' => $head->plGroup() !== $g])>
                                        {{ ['other' => 'Other', 'fixed' => 'Fixed', 'none' => 'Not counted'][$g] }}
                                    </button>
                                @endforeach
                            </form>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer small text-muted">"Not counted" keeps a head out of profit — e.g. Cash in (petty cash brought from the bank), loans, money moved between accounts.</div>
            </div>
        </div>
    </div>

</div>

<template id="pf-line">
    <tr>
        <td>
            <input type="hidden" data-name="section">
            <input type="hidden" data-name="source" value="">
            <input type="text" data-name="label" class="form-control" maxlength="120" required>
        </td>
        <td class="amt"><input type="number" data-name="amount" step="0.01" min="0" class="form-control js-amt" placeholder="0.00"></td>
        <td class="act"><button type="button" class="btn btn-link btn-sm p-0 pf-del js-del" title="Remove"><i class="fas fa-times"></i></button></td>
    </tr>
</template>

@stop

@section('js')
@unless ($final)
<script>
(function () {
    var form = document.getElementById('pf-form');
    var counter = {{ $i }};
    var r2 = function (v) { var n = Number(v) || 0; return Math.sign(n) * Math.round((Math.abs(n) + Number.EPSILON) * 100) / 100; };
    var lakh = function (v) { var n = r2(v); return (n < 0 ? '−' : '') + Math.abs(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var set = function (sel, text) { document.querySelectorAll(sel).forEach(function (el) { el.textContent = text; }); };

    // Same sums as the server; the saved figures are always the server's.
    function recalc() {
        var sec = { bill: 0, other_income: 0, fixed_cost: 0, other_cost: 0 };
        document.querySelectorAll('tbody[data-section]').forEach(function (tb) {
            tb.querySelectorAll('.js-amt').forEach(function (inp) { sec[tb.dataset.section] += r2(inp.value); });
        });

        var base = Math.max(0, sec.bill - sec.fixed_cost), commission = 0;
        document.querySelectorAll('tr[data-percent]').forEach(function (tr) {
            var amt = r2(base * Number(tr.dataset.percent) / 100);
            commission += amt;
            tr.querySelector('[data-amount]').textContent = lakh(amt);
        });

        var otherCost = sec.other_cost + commission, income = sec.bill + sec.other_income, cost = sec.fixed_cost + otherCost, net = income - cost;
        var shares = 0;
        document.querySelectorAll('tr[data-share]').forEach(function (tr) { shares += Number(tr.dataset.share) || 0; });
        var perShare = shares > 0 ? net / shares : 0;
        document.querySelectorAll('tr[data-share]').forEach(function (tr) { tr.querySelector('[data-amount]').textContent = lakh(perShare * Number(tr.dataset.share)); });

        ['bill', 'other_income', 'fixed_cost'].forEach(function (k) { set('[data-total="' + k + '"]', lakh(sec[k])); set('[data-sum="' + k + '"]', lakh(sec[k])); });
        set('[data-total="other_cost"]', lakh(otherCost));
        set('[data-sum="other_cost"]', lakh(otherCost));
        set('[data-sum="income"]', lakh(income));
        set('[data-sum="cost"]', lakh(cost));
        set('[data-sum="net"]', lakh(net));
        set('[data-net]', '৳' + lakh(net));
        set('[data-commission-total]', lakh(commission));
        set('[data-commission-base]', lakh(base));
        set('[data-per-share]', lakh(perShare));
        return { net: net, perShare: perShare };
    }

    form.addEventListener('input', recalc);

    form.addEventListener('click', function (e) {
        var del = e.target.closest('.js-del');
        if (del) { del.closest('tr').remove(); recalc(); return; }

        var add = e.target.closest('.js-add');
        if (add) {
            var tr = document.getElementById('pf-line').content.firstElementChild.cloneNode(true), n = counter++;
            tr.querySelectorAll('[data-name]').forEach(function (inp) { inp.name = 'lines[' + n + '][' + inp.dataset.name + ']'; });
            tr.querySelector('[data-name="section"]').value = add.dataset.section;
            tr.querySelector('[data-name="label"]').placeholder = add.dataset.placeholder;
            document.querySelector('tbody[data-section="' + add.dataset.section + '"]').appendChild(tr);
            tr.querySelector('[data-name="label"]').focus();
            return;
        }

        var fin = e.target.closest('.js-finalize');
        if (fin) {
            var r = recalc();
            if (!confirm('Finalize {{ $month->format('F Y') }}?\n\nNet Profit ৳' + lakh(r.net) + ' (৳' + lakh(r.perShare) + ' per share) will be credited to each partner\'s account and the month\'s books will be closed. Only an admin can reopen it.')) {
                e.preventDefault();
            }
        }
    });

    recalc();
})();
</script>
@endunless
@stop
