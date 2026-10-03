@extends('adminlte::page')

@section('title', $partner->name)

@use('App\Models\PartnerEntry')
@use('App\Support\Dec')
@php
    $L = fn ($v) => Dec::lakh($v ?? 0);
    $num = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
    $isAdmin = auth()->user()->isAdmin();
@endphp

@section('content_header')
<x-accounts.header :title="$partner->name" icon="fas fa-user-tie" :subtitle="$partner->roleLabel() . ' — account statement'" :back="route('accounts.partners.index')">
    <a href="{{ route('accounts.partners.print', $partner) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print statement</a>
</x-accounts.header>
@stop

@section('css')
<style>
    .ps-table td, .ps-table th { vertical-align: middle; }
    .ps-table th { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-top: 0; }
    .ps-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .ps-type { display: inline-block; padding: .1rem .5rem; border-radius: .4rem; font-size: .75rem; font-weight: 700; }
    .ps-type.share { background: #dcfce7; color: #15803d; }
    .ps-type.commission { background: #e0f2fe; color: #0369a1; }
    .ps-type.payment { background: #fef3c7; color: #b45309; }
    .ps-type.adjustment { background: #f1f5f9; color: #475569; }
</style>
@stop

@section('content')

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Credited <i class="fas fa-arrow-down"></i></div>
        <div class="acct-stat-value">৳{{ $L($earned) }}</div>
        <div class="acct-stat-foot">From finalized months</div>
    </div>
    <div class="acct-stat" style="--accent:#0284c7">
        <div class="acct-stat-label">Paid <i class="fas fa-hand-holding-usd"></i></div>
        <div class="acct-stat-value">৳{{ $L($paid) }}</div>
        <div class="acct-stat-foot">Handed over</div>
    </div>
    <div class="acct-stat acct-stat-hero">
        <div class="acct-stat-label">Balance <i class="fas fa-wallet"></i></div>
        <div class="acct-stat-value">৳{{ $L($balance) }}</div>
        <div class="acct-stat-foot">{{ Dec::isNegative($balance) ? 'Paid in advance' : 'Still to pay' }}</div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-book mr-1 text-primary"></i> Statement</h3>
                <span class="small text-muted">{{ $rows->count() }} {{ Str::plural('entry', $rows->count()) }}</span>
            </div>
            <div class="card-body p-0">
                @if ($rows->isEmpty())
                    <div class="acct-empty"><i class="fas fa-book"></i>Nothing yet — credits appear when a Net Profit month is finalized.</div>
                @else
                    <div class="table-responsive">
                        <table class="table ps-table mb-0">
                            <thead>
                                <tr><th class="pl-3">Date</th><th>Entry</th><th class="ps-num">Credit</th><th class="ps-num">Paid</th><th class="ps-num">Balance</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $e)
                                    @php $credit = ! Dec::isNegative($e->amount); @endphp
                                    <tr>
                                        <td class="pl-3 text-nowrap">{{ $e->entry_date->format('d M Y') }}</td>
                                        <td>
                                            <span class="ps-type {{ $e->type }}">{{ $e->typeLabel() }}</span>
                                            @if ($e->sheet)
                                                @can('access-accounts-profit')
                                                    <a href="{{ route('accounts.profit.index', ['month' => $e->sheet->month->format('Y-m')]) }}" class="small ml-1">{{ $e->sheet->month->format('M Y') }}</a>
                                                @else
                                                    <span class="small ml-1">{{ $e->sheet->month->format('M Y') }}</span>
                                                @endcan
                                            @endif
                                            @if ($e->method)<span class="small text-muted ml-1">· {{ $e->method }}</span>@endif
                                            @if ($e->transaction_id)<span class="small text-muted" title="Also in the Cash Book"> <i class="fas fa-book-open"></i></span>@endif
                                            <div class="small text-muted">{{ $e->note }}@if ($e->recorder) · {{ $e->recorder->name }}@endif</div>
                                        </td>
                                        <td class="ps-num text-income">{{ $credit ? $L($e->amount) : '' }}</td>
                                        <td class="ps-num">{{ $credit ? '' : $L(ltrim($e->amount, '-')) }}</td>
                                        <td class="ps-num font-weight-bold">{{ $L($e->running) }}</td>
                                        <td class="text-right pr-3">
                                            @if ($isAdmin && ! $e->isFromSheet())
                                                <form method="POST" action="{{ route('accounts.partners.entries.destroy', [$partner, $e]) }}" class="js-confirm-delete"
                                                      data-confirm-message="Remove this {{ strtolower($e->typeLabel()) }}{{ $e->transaction_id ? ' (and its Cash Book entry)' : '' }}?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-link btn-sm p-0 text-danger" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            @elseif ($e->isFromSheet())
                                                <i class="fas fa-lock text-muted small" title="From a finalized month"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-hand-holding-usd mr-1 text-success"></i> Record payment</h3></div>
            <form method="POST" action="{{ route('accounts.partners.entries.store', $partner) }}" class="card-body">
                @csrf
                <input type="hidden" name="type" value="payment">
                <div class="form-row">
                    <div class="col-6 form-group"><label>Date</label><input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="col-6 form-group"><label>Paid by</label>
                        <select name="method" class="form-control" required>
                            @foreach (PartnerEntry::METHODS as $m)<option @selected(old('method') === $m)>{{ $m }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Amount</label>
                    <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                        <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" class="form-control" placeholder="{{ Dec::isNegative($balance) ? '0.00' : Dec::round($balance, 2) }}" required></div>
                </div>
                <div class="form-group"><label>Note</label><input type="text" name="note" value="{{ old('note') }}" maxlength="255" class="form-control" placeholder="e.g. September profit share"></div>
                <button class="btn btn-success btn-block"><i class="fas fa-check"></i> Record payment</button>
                <small class="form-text text-muted">Paid out of the bank — it doesn't touch the petty cash and isn't a cost.</small>
            </form>
        </div>

        @if ($isAdmin)
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-user-edit mr-1 text-primary"></i> Details</h3></div>
                <form method="POST" action="{{ route('accounts.partners.update', $partner) }}" class="card-body">
                    @csrf @method('PUT')
                    <div class="form-group"><label>Name</label><input type="text" name="name" value="{{ $partner->name }}" class="form-control" maxlength="80" required></div>
                    <div class="form-row">
                        <div class="col-6 form-group"><label>Share</label><input type="number" name="share" step="0.001" min="0" value="{{ $num($partner->share) }}" class="form-control"></div>
                        <div class="col-6 form-group"><label>Commission %</label><input type="number" name="commission_percent" step="0.001" min="0" max="100" value="{{ $num($partner->commission_percent) }}" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" value="{{ $partner->phone }}" class="form-control" maxlength="30"></div>
                    <div class="form-group form-check"><input type="checkbox" name="is_active" value="1" id="pt-active" class="form-check-input" @checked($partner->is_active)><label for="pt-active" class="form-check-label">Active</label></div>
                    <button class="btn btn-outline-primary btn-block"><i class="fas fa-save"></i> Save</button>
                    <small class="form-text text-muted">Applies to months not finalized yet.</small>
                </form>
            </div>

            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-balance-scale mr-1 text-secondary"></i> Adjustment</h3></div>
                <form method="POST" action="{{ route('accounts.partners.entries.store', $partner) }}" class="card-body">
                    @csrf
                    <input type="hidden" name="type" value="adjustment">
                    <div class="form-row">
                        <div class="col-6 form-group"><label>Date</label><input type="date" name="entry_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
                        <div class="col-6 form-group"><label>Amount ±</label><input type="number" name="amount" step="0.01" class="form-control" placeholder="+ adds, − takes off" required></div>
                    </div>
                    <div class="form-group"><label>Reason</label><input type="text" name="note" maxlength="255" class="form-control" required placeholder="e.g. opening balance before October 2026"></div>
                    <button class="btn btn-light border btn-block">Record adjustment</button>
                </form>
            </div>
        @endif
    </div>
</div>

@stop
