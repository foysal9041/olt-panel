@extends('adminlte::page')

@section('title', 'Daily Cash Book')

@use('App\Support\Ui')
@php
    $tk = fn ($v) => '৳' . number_format((float) $v, 2);
    $prev = $date->copy()->subDay()->toDateString();
    $next = $date->copy()->addDay()->toDateString();
    // Ledger heads are data (often Bangla); show them in the interface language.
    $head = fn ($name) => Ui::t($name);
    $sides = [
        ['income', 'Cash in', 'Petty cash from the bank', $income, '#15803d', '#dcfce7', 'fas fa-arrow-down', 'Source / description', 'Total cash in'],
        ['expense', 'Expense', 'Office expenses', $expense, '#b91c1c', '#fee2e2', 'fas fa-arrow-up', 'Expense head / description', 'Total spent'],
    ];
@endphp

@section('content_header')
<x-accounts.header title="Daily Cash Book" icon="fas fa-book-open"
    subtitle="Petty cash book — {{ Ui::day($date) }}, {{ $date->format('d/m/Y') }}">
    @unless ($locked)
        <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#cash-modal">
            <i class="fas fa-wallet"></i> Cash on Hand
        </button>
    @endunless
    <a href="{{ route('accounts.cashbook.index', ['date' => $date->toDateString(), 'print' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-print"></i> Print
    </a>
</x-accounts.header>
@stop

@section('css')
<style>
    /* Day bar */
    .cb-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; padding: .8rem 1rem; margin-bottom: 1rem; border-radius: .85rem; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    .cb-day { display: flex; align-items: center; gap: .85rem; }
    .cb-day-icon { display: grid; place-items: center; width: 2.9rem; height: 2.9rem; border-radius: .75rem; background: #eef2ff; color: #4338ca; line-height: 1; text-align: center; }
    .cb-day-icon b { display: block; font-size: 1.15rem; }
    .cb-day-icon small { display: block; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .cb-day-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .cb-day-sub { font-size: .8rem; color: #64748b; }
    .cb-nav { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
    .cb-nav .input-group { width: auto; flex-wrap: nowrap; }
    .cb-nav input[type=date] { width: 10.5rem; }
    .cb-chips { display: flex; gap: .4rem; flex-wrap: wrap; }
    .cb-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .65rem; border-radius: 999px; font-size: .76rem; font-weight: 600; background: #f1f5f9; color: #475569; white-space: nowrap; }
    .cb-chip.open { background: #dcfce7; color: #15803d; }
    .cb-chip.locked { background: #e2e8f0; color: #334155; }
    .cb-chip.cash { background: #e0f2fe; color: #0369a1; }

    /* Balance flow: brought forward + cash in = available − spent = balance */
    .cb-flow { display: flex; align-items: stretch; gap: .6rem; margin-bottom: 1.25rem; }
    .cb-flow .acct-stat { flex: 1 1 0; min-width: 0; margin: 0; }
    .cb-flow .acct-stat-value { font-size: 1.35rem; }
    .cb-op { flex: none; align-self: center; display: grid; place-items: center; width: 1.8rem; height: 1.8rem; border-radius: 50%; background: #fff; color: #64748b; font-weight: 700; box-shadow: 0 1px 3px rgba(15, 23, 42, .1); }
    .cb-flow .acct-stat-hero.neg { background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 100%); }
    @media (max-width: 1199.98px) {
        .cb-flow { flex-wrap: wrap; }
        .cb-flow .acct-stat { flex: 1 1 calc(50% - .6rem); }
        .cb-flow .final { flex-basis: 100%; }
        .cb-op { display: none; }
    }
    @media (max-width: 575.98px) { .cb-flow .acct-stat { flex-basis: 100%; } }

    /* Ledger panels */
    .cb-side { --tone: #15803d; --tint: #dcfce7; overflow: hidden; }
    .cb-side .card-header { gap: .75rem; }
    .cb-head { display: flex; align-items: center; gap: .65rem; }
    .cb-head-icon { display: grid; place-items: center; width: 2.3rem; height: 2.3rem; border-radius: .65rem; background: var(--tint); color: var(--tone); }
    .cb-head-title { font-size: 1.15rem; font-weight: 700; color: var(--tone); line-height: 1.1; }
    .cb-head-title small { font-size: .78rem; font-weight: 600; color: #94a3b8; }
    .cb-head-total { text-align: right; line-height: 1.15; }
    .cb-head-total b { display: block; font-size: 1.2rem; color: var(--tone); font-variant-numeric: tabular-nums; }
    .cb-head-total span { font-size: .75rem; color: #94a3b8; }
    .cb-scroll { max-height: 27rem; overflow-y: auto; }
    .cb-table { margin: 0; }
    .cb-table thead th { position: sticky; top: 0; z-index: 1; background: #f8fafc; border-top: 0; border-bottom: 1px solid #e2e8f0; font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
    .cb-table td { vertical-align: middle; border-top: 1px solid #f1f5f9; padding-top: .55rem; padding-bottom: .55rem; }
    .cb-table tbody tr:hover { background: #fafafa; }
    .cb-table .amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cb-no { width: 2.6rem; color: #94a3b8; font-size: .8rem; }
    .cb-desc { font-weight: 600; color: #0f172a; }
    .cb-meta { margin-top: .1rem; font-size: .75rem; color: #94a3b8; }
    .cb-khat { display: inline-block; padding: .05rem .45rem; margin-right: .25rem; border-radius: .35rem; background: var(--tint); color: var(--tone); font-weight: 600; }
    .cb-del { opacity: .35; transition: opacity .15s; }
    .cb-table tr:hover .cb-del { opacity: 1; }
    .cb-total td { background: #f8fafc; font-weight: 700; border-top: 2px solid #e2e8f0; }
    .cb-total .amt { color: var(--tone); }
    .cb-add { background: #fbfcfe; border-top: 1px solid #eef2f7; }
    .cb-add .form-control, .cb-add .input-group-text { font-size: .86rem; }
    .cb-add label { margin-bottom: .15rem; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
    .cb-add .btn-add { background: var(--tone); border-color: var(--tone); color: #fff; }
    .cb-add .btn-add:hover { filter: brightness(.95); color: #fff; }

    /* Head-wise summary */
    .cb-heads { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0; }
    .cb-heads > div { padding: .9rem 1.15rem; }
    .cb-heads > div + div { border-left: 1px solid #eef2f7; }
    .cb-heads h6 { margin-bottom: .7rem; font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .cb-heads .acct-progress { margin-top: .3rem; height: .3rem; }
    @media (max-width: 767.98px) { .cb-heads > div + div { border-left: 0; border-top: 1px solid #eef2f7; } }
</style>
@stop

@section('content')

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

{{-- ============ Day bar ============ --}}
<div class="cb-bar">
    <div class="cb-day">
        <div class="cb-day-icon"><div><b>{{ $date->format('d') }}</b><small>{{ $date->format('M') }}</small></div></div>
        <div>
            <div class="cb-day-title">{{ Ui::day($date) }}, {{ Ui::longDate($date) }}</div>
            <div class="cb-day-sub">{{ $date->format('d/m/Y') }}@if ($date->isToday()) · <strong class="text-primary">Today</strong>@endif</div>
        </div>
    </div>

    <form method="GET" class="cb-nav">
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <a href="{{ route('accounts.cashbook.index', ['date' => $prev]) }}" class="btn btn-light border" title="Previous day"><i class="fas fa-chevron-left"></i></a>
            </div>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" onchange="this.form.submit()" aria-label="Date">
            <div class="input-group-append">
                <a href="{{ route('accounts.cashbook.index', ['date' => $next]) }}" class="btn btn-light border" title="Next day"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        @unless ($date->isToday())
            <a href="{{ route('accounts.cashbook.index') }}" class="btn btn-sm btn-outline-primary">Today</a>
        @endunless
    </form>

    <div class="cb-chips">
        @if ($locked)
            <span class="cb-chip locked" title="{{ \App\Models\Transaction::lockReason($date) }}"><i class="fas fa-lock"></i> {{ \App\Models\ProfitSheet::isMonthClosed($date) ? 'Month closed' : 'Locked day' }}</span>
        @else
            <span class="cb-chip open"><i class="fas fa-lock-open"></i> Open for entries</span>
        @endif
        @if ($countToday)
            <span class="cb-chip cash"><i class="fas fa-wallet"></i> Cash counted</span>
        @endif
        <span class="cb-chip"><i class="fas fa-list-ul"></i> {{ $income->count() + $expense->count() }} entries</span>
    </div>
</div>

@if ($locked)
    <div class="alert alert-light border d-flex align-items-center small" style="gap:.6rem">
        <i class="fas fa-lock text-muted"></i>
        <div><strong>Locked.</strong> {{ \App\Models\Transaction::lockReason($date) }} You can still view and print this day.</div>
    </div>
@endif

{{-- ============ Balance flow ============ --}}
<div class="cb-flow">
    <div class="acct-stat" style="--accent:#64748b">
        <div class="acct-stat-label">Brought forward <i class="fas fa-history"></i></div>
        <div class="acct-stat-value {{ $totals['opening'] < 0 ? 'text-danger' : '' }}">{{ $tk($totals['opening']) }}</div>
        <div class="acct-stat-foot">
            @if ($countToday)
                <span class="text-success"><i class="fas fa-wallet"></i> Cash counted today</span>
            @elseif ($base)
                From cash count on {{ $base->date->format('d/m/Y') }}
            @elseif (! $locked)
                <a href="#" data-toggle="modal" data-target="#cash-modal">Set cash on hand</a>
            @else
                Before this day
            @endif
        </div>
    </div>
    <span class="cb-op">+</span>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Cash in today <i class="fas fa-arrow-down"></i></div>
        <div class="acct-stat-value text-income">{{ $tk($totals['day_income']) }}</div>
        <div class="acct-stat-foot">{{ $income->count() }} {{ Ui::t(Str::plural('entry', $income->count())) }}</div>
    </div>
    <span class="cb-op">=</span>
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">Available <i class="fas fa-layer-group"></i></div>
        <div class="acct-stat-value">{{ $tk($totals['total_income']) }}</div>
        <div class="acct-stat-foot">Brought forward + cash in</div>
    </div>
    <span class="cb-op">−</span>
    <div class="acct-stat" style="--accent:#e11d48">
        <div class="acct-stat-label">Spent <i class="fas fa-arrow-up"></i></div>
        <div class="acct-stat-value text-expense">{{ $tk($totals['total_expense']) }}</div>
        <div class="acct-stat-foot">{{ $expense->count() }} {{ Ui::t(Str::plural('entry', $expense->count())) }}</div>
    </div>
    <span class="cb-op">=</span>
    <div @class(['acct-stat', 'acct-stat-hero', 'final', 'neg' => $totals['closing'] < 0])>
        <div class="acct-stat-label">Balance <i class="fas fa-wallet"></i></div>
        <div class="acct-stat-value">{{ $tk($totals['closing']) }}</div>
        <div class="acct-stat-foot">Petty cash in hand at day end</div>
    </div>
</div>

{{-- ============ Cash in / Expense ============ --}}
<div class="row">
    @foreach ($sides as [$type, $label, $subLabel, $list, $tone, $tint, $icon, $colLabel, $totalLabel])
        <div class="col-xl-6">
            <div class="card acct-panel cb-side" style="--tone: {{ $tone }}; --tint: {{ $tint }}">
                <div class="card-header">
                    <div class="cb-head">
                        <span class="cb-head-icon"><i class="{{ $icon }}"></i></span>
                        <div class="cb-head-title">{{ $label }} <small>{{ $subLabel }}</small></div>
                    </div>
                    <div class="cb-head-total">
                        <b>{{ $tk($list->sum('amount')) }}</b>
                        <span>{{ $list->count() }} {{ Ui::t(Str::plural('entry', $list->count())) }}</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="cb-scroll">
                        <table class="table table-sm cb-table">
                            <thead>
                                <tr>
                                    <th class="pl-3">No.</th>
                                    <th>{{ $colLabel }}</th>
                                    <th class="amt">Amount</th>
                                    <th style="width:2.2rem"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($list as $t)
                                    <tr>
                                        <td class="pl-3 cb-no">{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="cb-desc">{{ $t->description ?: $head($t->category->name) }}</div>
                                            <div class="cb-meta">
                                                <span class="cb-khat">{{ $head($t->category->name) }}</span>
                                                {{ $t->recordedBy?->name ?? '—' }} · {{ $t->created_at?->format('h:i A') }}
                                            </div>
                                        </td>
                                        <td class="amt font-weight-bold">{{ $tk($t->amount) }}</td>
                                        <td class="text-right pr-3">
                                            @unless ($locked)
                                                <form method="POST" action="{{ route('accounts.cashbook.destroy', $t) }}" class="d-inline js-confirm-delete"
                                                      data-confirm-message="{{ Ui::t('Remove') }} {{ $head($t->category->name) }} {{ $tk($t->amount) }}?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-link btn-sm text-danger p-0 cb-del" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="border-0"><div class="acct-empty"><i class="{{ $icon }}"></i>No entries on this day</div></td></tr>
                                @endforelse
                            </tbody>
                            @if ($list->isNotEmpty())
                                <tfoot>
                                    <tr class="cb-total">
                                        <td class="pl-3" colspan="2">{{ $totalLabel }}</td>
                                        <td class="amt">{{ $tk($list->sum('amount')) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
                @unless ($locked)
                    <div class="card-footer cb-add">
                        <form method="POST" action="{{ route('accounts.cashbook.store') }}" class="form-row align-items-end">
                            @csrf
                            <input type="hidden" name="transaction_date" value="{{ $date->toDateString() }}">
                            <input type="hidden" name="_type" value="{{ $type }}">
                            <div class="col-md-4 mb-2">
                                <label>Head</label>
                                <div class="input-group input-group-sm">
                                    <select name="transaction_category_id" class="form-control" required>
                                        <option value="">Select…</option>
                                        @foreach ($categories[$type] ?? [] as $c)
                                            <option value="{{ $c->id }}" @selected(old('transaction_category_id') == $c->id && old('_type') === $type)>{{ $head($c->name) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-light border" data-toggle="modal" data-target="#khat-{{ $type }}" title="{{ Ui::t('Add or remove heads') }}"><i class="fas fa-cog"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label>Description</label>
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="Description" maxlength="255"
                                       value="{{ old('_type') === $type ? old('description') : '' }}">
                            </div>
                            <div class="col-md-2 col-7 mb-2">
                                <label>Amount</label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                                    <input type="number" name="amount" step="0.01" min="0.01" class="form-control" placeholder="0.00" required
                                           value="{{ old('_type') === $type ? old('amount') : '' }}">
                                </div>
                            </div>
                            <div class="col-md-2 col-5 mb-2">
                                <button class="btn btn-sm btn-block btn-add"><i class="fas fa-plus"></i> {{ $label }}</button>
                            </div>
                        </form>
                    </div>
                @endunless
            </div>
        </div>
    @endforeach
</div>

{{-- ============ By head ============ --}}
@if ($income->isNotEmpty() || $expense->isNotEmpty())
    <div class="card acct-panel">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tags mr-1 text-primary"></i> By head</h3>
        </div>
        <div class="cb-heads">
            @foreach ($sides as [$type, $label, $subLabel, $list, $tone])
                @php
                    $dayTotal = (float) $list->sum('amount');
                    $heads = $list->groupBy(fn ($t) => $head($t->category->name))
                        ->map(fn ($g, $name) => ['name' => $name, 'sum' => (float) $g->sum('amount'), 'count' => $g->count()])
                        ->sortByDesc('sum');
                @endphp
                <div>
                    <h6 style="color: {{ $tone }}">{{ $label }} · {{ $subLabel }}</h6>
                    @forelse ($heads as $h)
                        <div class="acct-bar-row">
                            <div class="d-flex justify-content-between">
                                <span>{{ $h['name'] }} <span class="text-muted small">× {{ $h['count'] }}</span></span>
                                <strong class="money">{{ $tk($h['sum']) }}</strong>
                            </div>
                            <div class="acct-progress"><span style="width: {{ $dayTotal > 0 ? round($h['sum'] / $dayTotal * 100, 1) : 0 }}%; background: {{ $tone }}"></span></div>
                        </div>
                    @empty
                        <div class="text-muted small">Nothing on this day.</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>
@endif

<p class="small text-muted">
    <i class="fas fa-info-circle"></i>
    This is the <strong>petty cash</strong>: all income is deposited in the bank, the office gets petty cash from the bank (<strong>Cash in</strong>)
    and pays its expenses out of it (<strong>Expense</strong>). Bills paid straight from the bank go in Income &amp; Expenses as <em>Bank</em>.
    Brought forward = petty cash received minus spent before this day. Entries here are the same as in
    @can('access-accounts-transactions') <a href="{{ route('accounts.transactions.index') }}">Income &amp; Expenses</a> @else Income &amp; Expenses @endcan
    and the monthly sheets, so each entry is made once.
</p>

{{-- ============ Cash on hand ============ --}}
@unless ($locked)
<div class="modal fade" id="cash-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('accounts.cashbook.opening.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-wallet text-primary"></i> Cash on Hand</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Count the <strong>petty cash</strong> in the office (not the bank) at the <strong>start</strong> of this day and enter it here. The brought-forward balance for this day starts from this amount,
                    and later days add their cash in and subtract their expenses from it. Set it again any day the count doesn't match.
                </p>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" required
                           @unless (auth()->user()->isAdmin()) min="{{ now()->toDateString() }}" @endunless>
                </div>
                <div class="form-group">
                    <label>Cash on hand at the start of the day</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                        <input type="number" step="0.01" name="amount" class="form-control" required
                               value="{{ $countToday ? $countToday->amount : $totals['opening'] }}">
                    </div>
                    @unless ($countToday)
                        <small class="form-text text-muted">Filled with the brought-forward balance worked out now ({{ $tk($totals['opening']) }}) — change it to what you actually counted.</small>
                    @endunless
                </div>
                <div class="form-group mb-0">
                    <label>Note</label>
                    <input type="text" name="note" value="{{ $countToday?->note }}" class="form-control" placeholder="optional, e.g. counted by …" maxlength="255">
                </div>
                @if ($base)
                    <div class="small text-muted mt-3"><i class="fas fa-history"></i>
                        Last count: {{ $tk($base->amount) }} on {{ $base->date->format('d/m/Y') }}{{ $base->note ? ' — ' . $base->note : '' }}
                    </div>
                @endif
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    @if ($countToday)
                        <button type="submit" form="remove-count" class="btn btn-link text-danger p-0"><i class="fas fa-trash"></i> Remove this count</button>
                    @endif
                </div>
                <div>
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if ($countToday)
    <form id="remove-count" method="POST" action="{{ route('accounts.cashbook.opening.destroy', $countToday) }}" class="js-confirm-delete"
          data-confirm-message="Remove the cash count for {{ $countToday->date->format('d/m/Y') }}?">
        @csrf
        @method('DELETE')
    </form>
@endif
@endunless

{{-- ============ Ledger heads ============ --}}
@foreach (['income' => ['Cash-in heads', '#15803d'], 'expense' => ['Expense heads', '#b91c1c']] as $type => [$label, $tone])
    <div class="modal fade" id="khat-{{ $type }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="color: {{ $tone }}">{{ $label }}</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('accounts.cashbook.categories.store') }}" class="d-flex mb-3" style="gap:.5rem">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <input type="text" name="name" class="form-control" placeholder="{{ Ui::t($type === 'income' ? 'New head, e.g. Bank withdrawal' : 'New head, e.g. Electricity bill') }}" required maxlength="255">
                        <button class="btn text-nowrap" style="background: {{ $tone }}; color: #fff"><i class="fas fa-plus"></i> Add</button>
                    </form>
                    <ul class="list-group">
                        @foreach ($categories[$type] ?? [] as $c)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                {{ $head($c->name) }}
                                <form method="POST" action="{{ route('accounts.cashbook.categories.destroy', $c) }}" class="js-confirm-delete"
                                      data-confirm-message="{{ Ui::t('Remove head') }} “{{ $head($c->name) }}”?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link btn-sm text-danger p-0" title="Remove (only if it has no entries)"><i class="fas fa-times"></i></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <small class="text-muted d-block mt-2">A head that already has entries can't be removed.</small>
                </div>
            </div>
        </div>
    </div>
@endforeach

@stop
