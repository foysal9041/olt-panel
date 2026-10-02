@extends('adminlte::page')

@section('title', 'Zone Settlement ' . $settlement->month->format('F Y'))

@use('App\Support\Dec')
@php
    $pct = rtrim(rtrim($settlement->bkash_percent, '0'), '.');
    $counted = $settlement->rows->where('included', true);
    $notCounted = $settlement->rows->where('included', false);
    $flagIcon = ['error' => ['badge-danger', 'fas fa-times-circle'], 'warn' => ['badge-warning', 'fas fa-exclamation-triangle'], 'info' => ['badge-secondary', 'fas fa-info-circle']];
@endphp

@section('content_header')
<x-accounts.header :title="'Zone Settlement — ' . $settlement->month->format('F Y')" :back="route('accounts.settlements.index')"
    :subtitle="$settlement->source_name . ' · invoice date ' . $settlement->invoice_date->format('d M Y') . ' · bKash ' . $pct . '%'">
    <a href="{{ route('accounts.settlements.invoices', $settlement) }}" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print all invoices</a>
    <a href="{{ route('accounts.settlements.export', $settlement) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Download Excel</a>
</x-accounts.header>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row">
    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-list mr-1 text-primary"></i> Grand Summary</h3></div>
            <div class="card-body p-0">
                @foreach ([
                    ['Total Customers/Zones', $totals['count'], false],
                    ['Total Payment', Dec::taka($totals['payment']), false],
                    ['Total Deduction', Dec::taka($totals['deduction']), false],
                    ['Total Payable (Customer Invoice)', Dec::taka($totals['invoice']), false],
                    ['Total Payment Difference', Dec::taka($totals['difference']), false],
                    ["Total bKash Charge ({$pct}%)", Dec::taka($totals['bkash']), false],
                    ['Net Bill total (Company Income)', Dec::taka($totals['income']), true],
                ] as [$label, $value, $strong])
                    <div class="acct-list-row" @if ($strong) style="background:#f0fdf4" @endif>
                        <div class="acct-list-main small {{ $strong ? 'font-weight-bold text-success' : 'text-muted' }}">{{ $label }}</div>
                        <div class="money {{ $strong ? 'font-weight-bold text-success' : 'font-weight-bold' }}" style="color:#0f172a">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            <div class="card-footer small text-muted">
                Saved by {{ $settlement->creator?->name ?? '—' }}, {{ $settlement->created_at->format('d M Y, h:i A') }}
                @if ($settlement->notes)<div class="mt-1">{{ $settlement->notes }}</div>@endif
            </div>
        </div>

        {{-- Company income → Accounts (one income entry per zone under "Zone Settlement") --}}
        @if ($settlement->isPosted())
            @php
                $posted = $settlement->rows->pluck('transaction')->filter();
                $postedCategory = $posted->first()?->transaction_category_id;
                $postedSum = $posted->reduce(fn ($t, $x) => Dec::add($t, $x->amount), '0');
            @endphp
            <div class="card acct-panel" style="border-left:3px solid #16a34a">
                <div class="card-body py-2 small">
                    <div class="font-weight-bold text-success mb-1"><i class="fas fa-check-circle"></i> Posted to Income</div>
                    <div><span class="money font-weight-bold" style="color:#0f172a">{{ Dec::taka($postedSum) }}</span>
                        as {{ $posted->count() }} {{ Str::plural('entry', $posted->count()) }} under <strong>Zone Settlement</strong></div>
                    @if ($posted->count() === $totals['count'] && Dec::round($postedSum, 2) !== Dec::round($totals['income'], 2))
                        <div class="text-muted" style="font-size:.75rem">Each entry is rounded to the paisa like its invoice, so the sum differs slightly from the summary's {{ Dec::taka($totals['income']) }}.</div>
                    @endif
                    <div class="text-muted">Dated {{ $settlement->posted_on->format('d M Y') }} · by {{ $settlement->poster?->name ?? '—' }}, {{ $settlement->posted_at->format('d M, h:i A') }}</div>
                    @if ($posted->count() < $totals['count'])
                        <div class="text-warning mt-1"><i class="fas fa-exclamation-triangle"></i> {{ $totals['count'] - $posted->count() }} zone(s) have no income entry (zero Net Bill, or the entry was deleted in Transactions).</div>
                    @endif
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center py-2" style="gap:.4rem">
                    @can('access-accounts-transactions')
                        <a href="{{ route('accounts.transactions.index', array_filter(['start' => $settlement->posted_on->toDateString(), 'end' => $settlement->posted_on->toDateString(), 'type' => 'income', 'category_id' => $postedCategory])) }}" class="btn btn-light btn-sm"><i class="fas fa-list"></i> View entries</a>
                    @endcan
                    @can('access-accounts-cashbook')
                        <a href="{{ route('accounts.cashbook.index', ['date' => $settlement->posted_on->toDateString()]) }}" class="btn btn-light btn-sm"><i class="fas fa-book"></i> Cash book</a>
                    @endcan
                    @if (\App\Models\Transaction::dayIsOpenFor($settlement->posted_on, auth()->user()))
                        <form method="POST" action="{{ route('accounts.settlements.unpost', $settlement) }}" class="js-confirm-delete ml-auto"
                              data-confirm-message="Remove the {{ $settlement->month->format('F Y') }} Zone Settlement income entries from Accounts?">
                            @csrf @method('DELETE')
                            <button class="btn btn-link btn-sm text-danger p-0"><i class="fas fa-undo"></i> Undo posting</button>
                        </form>
                    @else
                        <span class="ml-auto small text-muted" title="Past days are closed books"><i class="fas fa-lock"></i> Admin can undo</span>
                    @endif
                </div>
            </div>
        @else
            <div class="card acct-panel" style="border-left:3px solid #2563eb">
                <div class="card-body py-2 small">
                    <div class="font-weight-bold mb-1"><i class="fas fa-hand-holding-usd text-primary"></i> Post income to Accounts</div>
                    <div class="text-muted mb-2">Adds each zone's Net Bill as income under <strong>Zone Settlement</strong> — {{ $totals['count'] }} entries, {{ Dec::taka($totals['income']) }} in all.</div>
                    <form method="POST" action="{{ route('accounts.settlements.post', $settlement) }}" class="form-row align-items-end"
                          onsubmit="return confirm('Post {{ Dec::taka($totals['income']) }} to income as {{ $totals['count'] }} Zone Settlement entries?')">
                        @csrf
                        <div class="col-7 form-group mb-0">
                            <label class="small mb-0 font-weight-bold">Income date</label>
                            <input type="date" name="income_date" value="{{ old('income_date', $settlement->invoice_date->toDateString()) }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-5 form-group mb-0">
                            <button class="btn btn-success btn-sm btn-block"><i class="fas fa-check"></i> Post</button>
                        </div>
                    </form>
                    @unless (auth()->user()->isAdmin())
                        <div class="text-muted mt-1" style="font-size:.75rem"><i class="fas fa-info-circle"></i> Past days are closed books — only an admin can post to a date before today.</div>
                    @endunless
                </div>
            </div>
        @endif

        @if ($reconcile)
            @php $allOk = collect($reconcile)->every('ok'); @endphp
            <div class="alert {{ $allOk ? 'alert-success' : 'alert-warning' }} small">
                <strong>Sheet's own Total row:</strong>
                @foreach ($reconcile as $c)
                    <div>{{ $c['label'] }}: sheet {{ Dec::taka($c['sheet']) }}, counted {{ Dec::taka($c['ours']) }} {{ $c['ok'] ? '✔' : '✘' }}</div>
                @endforeach
            </div>
        @endif

        <div class="card acct-panel">
            <div class="card-body small">
                <div class="mb-1"><strong>Total Payable</strong> (Customer Invoice) = Total Payment + Deduction</div>
                <div class="mb-1"><strong>Payment Difference</strong> = Total Payment − Total Payable</div>
                <div class="mb-1"><strong>Bkash Charge</strong> = Total Payment × {{ $pct }}%</div>
                <div class="mb-2"><strong>Net Bill</strong> (Company Income) = Payment Difference − Bkash Charge</div>
                <div class="text-muted">So Total Payment = Net Bill + Bkash Charge + Total Payable.</div>
            </div>
            <div class="card-body border-top small">
                <form method="POST" action="{{ route('accounts.settlements.signatory', $settlement) }}" class="form-row align-items-end">
                    @csrf @method('PUT')
                    <div class="col-6 form-group mb-1">
                        <label class="small mb-0 font-weight-bold">Prepared By</label>
                        <input type="text" name="prepared_by" value="{{ $settlement->prepared_by }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-4 form-group mb-1">
                        <label class="small mb-0 font-weight-bold">Title</label>
                        <input type="text" name="prepared_title" value="{{ $settlement->prepared_title }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-2 form-group mb-1"><button class="btn btn-light btn-sm btn-block" title="Save"><i class="fas fa-save"></i></button></div>
                </form>
            </div>
            <div class="card-footer d-flex flex-wrap" style="gap:.4rem">
                @if ($settlement->source_path)
                    <a href="{{ route('accounts.settlements.source', $settlement) }}" class="btn btn-light btn-sm"><i class="fas fa-download"></i> Original file</a>
                @endif
                @if ($settlement->isPosted())
                    <span class="ml-auto small text-muted align-self-center" title="Undo the income posting first"><i class="fas fa-lock"></i> Posted — undo to delete</span>
                @else
                    <form method="POST" action="{{ route('accounts.settlements.destroy', $settlement) }}" class="js-confirm-delete ml-auto"
                          data-confirm-message="Delete the {{ $settlement->month->format('F Y') }} settlement and its {{ $counted->count() }} invoices?">
                        @csrf @method('DELETE')
                        <button class="btn btn-light btn-sm text-danger"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-table mr-1 text-primary"></i> Customer-wise calculation</h3>
                <span class="small text-muted">{{ $counted->count() }} invoices</span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="pl-3">Invoice</th>
                                <th>Customer/Zone</th>
                                <th class="text-right">Total Payment</th>
                                <th class="text-right">Deduction</th>
                                <th class="text-right">Total Payable</th>
                                <th class="text-right">Difference</th>
                                <th class="text-right">bKash</th>
                                <th class="text-right pr-3">Net Bill</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($counted as $row)
                                @php $c = $row->calc($settlement->bkash_percent); @endphp
                                <tr>
                                    <td class="pl-3 text-nowrap"><a href="{{ route('accounts.settlements.invoice', [$settlement, $row]) }}" target="_blank" title="Print invoice"><i class="fas fa-print small"></i> {{ $row->invoice_no }}</a></td>
                                    <td>
                                        @if ($row->customer)
                                            <a href="{{ route('accounts.customers.show', $row->customer) }}" style="color:#0f172a">{{ $row->displayName() }}</a>
                                        @else
                                            {{ $row->displayName() }}
                                        @endif
                                        @if ($row->username())<span class="small text-muted">({{ $row->username() }})</span>@endif
                                        @foreach ($row->flags ?? [] as $f)
                                            <div class="small"><span class="badge {{ $flagIcon[$f['level']][0] ?? 'badge-light' }}"><i class="{{ $flagIcon[$f['level']][1] ?? '' }}"></i></span> {{ $f['text'] }}</div>
                                        @endforeach
                                    </td>
                                    <td class="text-right money">{{ Dec::taka($c['payment']) }}</td>
                                    <td class="text-right money">{{ Dec::taka($c['deduction']) }}</td>
                                    <td class="text-right money font-weight-bold">{{ Dec::taka($c['invoice']) }}</td>
                                    <td class="text-right money">{{ Dec::taka($c['difference']) }}</td>
                                    <td class="text-right money">{{ Dec::taka($c['bkash']) }}</td>
                                    <td class="text-right money font-weight-bold text-success pr-3">{{ Dec::taka($c['income']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold" style="background:#eef2ff">
                                <td class="pl-3" colspan="2">Grand Total — {{ $totals['count'] }}</td>
                                <td class="text-right money">{{ Dec::taka($totals['payment']) }}</td>
                                <td class="text-right money">{{ Dec::taka($totals['deduction']) }}</td>
                                <td class="text-right money">{{ Dec::taka($totals['invoice']) }}</td>
                                <td class="text-right money">{{ Dec::taka($totals['difference']) }}</td>
                                <td class="text-right money">{{ Dec::taka($totals['bkash']) }}</td>
                                <td class="text-right money text-success pr-3">{{ Dec::taka($totals['income']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        @if ($notCounted->isNotEmpty())
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-triangle mr-1 text-warning"></i> Not counted</h3>
                    <span class="small text-muted">left out of totals and invoices</span></div>
                <div class="card-body p-0">
                    @foreach ($notCounted as $row)
                        <div class="acct-list-row">
                            <span class="text-muted small" style="width:3.5rem">Row {{ $row->source_row }}</span>
                            <div class="acct-list-main">
                                <div class="acct-list-title">{{ $row->name ?: '— no name —' }}</div>
                                <div class="acct-list-sub">{{ collect($row->flags ?? [])->pluck('text')->implode(' · ') }}</div>
                            </div>
                            <div class="small money text-right">
                                {{ $row->total_payment !== null ? Dec::taka($row->total_payment) : '—' }}<br>
                                <span class="text-muted">{{ $row->deduction !== null ? Dec::taka($row->deduction) : '—' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@stop
