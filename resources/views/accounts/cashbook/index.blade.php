@extends('adminlte::page')

@section('title', 'Daily Cash Book')

@php
    $tk = fn ($v) => '৳' . number_format((float) $v, 2);
    $prev = $date->copy()->subDay()->toDateString();
    $next = $date->copy()->addDay()->toDateString();
@endphp

@section('content_header')
<x-accounts.header title="প্রতিদিনের হিসাব" icon="fas fa-book-open"
    subtitle="Daily Cash Book — {{ \App\Support\Bangla::day($date) }}, {{ $date->format('d/m/Y') }}">
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
    .cb-nav { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .cb-nav input[type=date] { width: auto; }
    .cb-side .card-header { border-bottom: 3px solid var(--tone); }
    .cb-side .card-title { font-size: 1.15rem; font-weight: 700; color: var(--tone); }
    .cb-table td, .cb-table th { vertical-align: middle; }
    .cb-table td.amt, .cb-table th.amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cb-table tfoot td { font-weight: 700; background: #f8fafc; }
    .cb-add { background: #f8fafc; }
    .cb-add .form-control { font-size: .88rem; }
    .cb-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; }
    .cb-sum div { padding: .8rem 1rem; border-radius: .7rem; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    .cb-sum span { display: block; font-size: .8rem; font-weight: 600; color: #64748b; }
    .cb-sum b { display: block; margin-top: .15rem; font-size: 1.35rem; font-variant-numeric: tabular-nums; color: #0f172a; }
    .cb-sum .final { background: #1e3a8a; }
    .cb-sum .final span { color: rgba(255, 255, 255, .75); }
    .cb-sum .final b { color: #fff; }
    .cb-empty td { color: #94a3b8; text-align: center; padding: 1.25rem; }
</style>
@stop

@section('content')

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<form method="GET" class="cb-nav">
    <a href="{{ route('accounts.cashbook.index', ['date' => $prev]) }}" class="btn btn-light"><i class="fas fa-chevron-left"></i> Previous day</a>
    <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" onchange="this.form.submit()">
    <a href="{{ route('accounts.cashbook.index', ['date' => $next]) }}" class="btn btn-light">Next day <i class="fas fa-chevron-right"></i></a>
    @unless ($date->isToday())
        <a href="{{ route('accounts.cashbook.index') }}" class="btn btn-link">Today</a>
    @endunless
</form>

@if ($locked)
    <div class="alert alert-secondary d-flex align-items-center" style="gap:.6rem">
        <i class="fas fa-lock fa-lg"></i>
        <div><strong>Locked day.</strong> Entries for past days can only be added, changed or removed by an admin. You can still view and print this day.</div>
    </div>
@endif

<div class="row">
    @foreach ([
        ['income', 'জমা', 'Income', $income, '#15803d', 'আয়ের উৎস', 'আয়ের পরিমান'],
        ['expense', 'খরচ', 'Expense', $expense, '#b91c1c', 'ব্যায়ের খাত', 'ব্যায়ের পরিমান'],
    ] as [$type, $bn, $en, $list, $tone, $colLabel, $amtLabel])
        <div class="col-lg-6">
            <div class="card cb-side" style="--tone: {{ $tone }}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">{{ $bn }} <small class="text-muted font-weight-normal">{{ $en }}</small></h3>
                    <strong style="color: {{ $tone }}">{{ $tk($list->sum('amount')) }}</strong>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm cb-table mb-0">
                        <thead>
                            <tr>
                                <th class="pl-3" style="width:2.5rem">নং</th>
                                <th>{{ $colLabel }}</th>
                                <th class="amt">{{ $amtLabel }}</th>
                                <th style="width:2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($list as $t)
                                <tr>
                                    <td class="pl-3 text-muted">{{ $loop->iteration }}</td>
                                    <td>
                                        <span class="badge badge-light border">{{ $t->category->name }}</span>
                                        {{ $t->description }}
                                    </td>
                                    <td class="amt font-weight-bold">{{ $tk($t->amount) }}</td>
                                    <td class="text-right pr-2">
                                        @if (! $t->invoice_id && ! $locked)
                                            <form method="POST" action="{{ route('accounts.cashbook.destroy', $t) }}" class="d-inline js-confirm-delete"
                                                  data-confirm-message="Remove {{ $t->category->name }} {{ $tk($t->amount) }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-link btn-sm text-danger p-0" title="Remove"><i class="fas fa-times"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="cb-empty"><td colspan="4">No {{ strtolower($en) }} entries on this day</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @unless ($locked)
                <div class="card-footer cb-add">
                    <form method="POST" action="{{ route('accounts.cashbook.store') }}" class="form-row align-items-end">
                        @csrf
                        <input type="hidden" name="transaction_date" value="{{ $date->toDateString() }}">
                        <div class="col-sm-4 mb-2 d-flex" style="gap:.25rem">
                            <select name="transaction_category_id" class="form-control form-control-sm" required>
                                <option value="">খাত…</option>
                                @foreach ($categories[$type] ?? [] as $c)
                                    <option value="{{ $c->id }}" @selected(old('transaction_category_id') == $c->id && old('_type') === $type)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-light border text-nowrap" data-toggle="modal" data-target="#khat-{{ $type }}" title="Add or remove {{ $bn }} খাত">
                                <i class="fas fa-plus"></i> খাত
                            </button>
                        </div>
                        <div class="col-sm-5 mb-2">
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="বিবরণ (description)" maxlength="255">
                        </div>
                        <div class="col-sm-3 mb-2">
                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="৳" required>
                        </div>
                        <input type="hidden" name="_type" value="{{ $type }}">
                        <div class="col-12">
                            <button class="btn btn-sm btn-block" style="background: {{ $tone }}; color: #fff">
                                <i class="fas fa-plus"></i> Add {{ $bn }}
                            </button>
                        </div>
                    </form>
                </div>
                @endunless
            </div>
        </div>
    @endforeach
</div>

<div class="cb-sum mb-4">
    <div>
        <span>জের (Brought forward)</span><b class="{{ $totals['opening'] < 0 ? 'text-danger' : '' }}">{{ $tk($totals['opening']) }}</b>
        @if ($countToday)
            <small class="text-success"><i class="fas fa-wallet"></i> cash counted today</small>
        @elseif ($base)
            <small class="text-muted">from cash count on {{ $base->date->format('d/m/Y') }}</small>
        @elseif (! $locked)
            <small class="text-muted"><a href="#" data-toggle="modal" data-target="#cash-modal">set cash on hand</a></small>
        @endif
    </div>
    <div><span>দিনের আয় (Today's income)</span><b class="text-success">{{ $tk($totals['day_income']) }}</b></div>
    <div><span>মোট আয় (Total)</span><b>{{ $tk($totals['total_income']) }}</b></div>
    <div><span>মোট ব্যয় (Today's expense)</span><b class="text-danger">{{ $tk($totals['total_expense']) }}</b></div>
    <div class="final"><span>অবশিষ্ট টাকা (Balance)</span><b>{{ $tk($totals['closing']) }}</b></div>
</div>

<p class="small text-muted">
    <i class="fas fa-info-circle"></i>
    জের = everything received minus everything spent before this day. Entries here are the same as in
    @can('access-accounts-transactions') <a href="{{ route('accounts.transactions.index') }}">Income &amp; Expenses</a> @else Income &amp; Expenses @endcan
    and the monthly sheets, so each expense is entered once.
</p>

{{-- ============ Cash on hand ============ --}}
@unless ($locked)
<div class="modal fade" id="cash-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('accounts.cashbook.opening.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-wallet text-primary"></i> Cash on Hand (হাতে নগদ)</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Count the cash at the <strong>start</strong> of this day and enter it here. The জের for this day starts from this amount,
                    and later days add their আয় and subtract their ব্যয় from it. Set it again any day the count doesn't match.
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
                        <small class="form-text text-muted">Filled with the জের worked out now ({{ $tk($totals['opening']) }}) — change it to what you actually counted.</small>
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

{{-- ============ খাত (ledger heads) ============ --}}
@foreach (['income' => ['আয়ের খাত', '#15803d'], 'expense' => ['ব্যয়ের খাত', '#b91c1c']] as $type => [$label, $tone])
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
                        <input type="text" name="name" class="form-control" placeholder="নতুন খাত, e.g. {{ $type === 'income' ? 'কাস্টমার বিল' : 'বিদ্যুৎ বিল' }}" required maxlength="255">
                        <button class="btn text-nowrap" style="background: {{ $tone }}; color: #fff"><i class="fas fa-plus"></i> Add</button>
                    </form>
                    <ul class="list-group">
                        @foreach ($categories[$type] ?? [] as $c)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                {{ $c->name }}
                                <form method="POST" action="{{ route('accounts.cashbook.categories.destroy', $c) }}" class="js-confirm-delete"
                                      data-confirm-message="Remove খাত “{{ $c->name }}”?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link btn-sm text-danger p-0" title="Remove (only if it has no entries)"><i class="fas fa-times"></i></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <small class="text-muted d-block mt-2">A খাত that already has entries can't be removed.</small>
                </div>
            </div>
        </div>
    </div>
@endforeach

@stop
