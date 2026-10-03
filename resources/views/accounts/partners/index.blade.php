@extends('adminlte::page')

@section('title', 'Partners')

@use('App\Support\Dec')
@php
    $L = fn ($v) => Dec::lakh($v ?? 0);
    $num = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
    $active = $partners->where('is_active', true);
    $sharesTotal = $active->sum(fn ($p) => (float) $p->share);
    $totals = ['earned' => '0', 'paid' => '0', 'balance' => '0'];
    foreach ($sums as $row) {
        foreach ($totals as $k => $v) {
            $totals[$k] = Dec::add($v, $row->$k);
        }
    }
    $isAdmin = auth()->user()->isAdmin();
@endphp

@section('content_header')
<x-accounts.header title="Partners" icon="fas fa-user-tie" subtitle="Shareholders and commission holders — each one's own account" />
@stop

@section('css')
<style>
    .pt-table td, .pt-table th { vertical-align: middle; }
    .pt-table th { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-top: 0; }
    .pt-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .pt-avatar { display: inline-grid; place-items: center; width: 2.2rem; height: 2.2rem; border-radius: .65rem; background: #eef2ff; color: #4338ca; font-weight: 800; font-size: .8rem; text-transform: uppercase; margin-right: .6rem; }
    .pt-name { font-weight: 700; color: #0f172a; }
    .pt-name:hover { color: #4f46e5; text-decoration: none; }
    tr.inactive { opacity: .55; }
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
        <div class="acct-stat-value">৳{{ $L($totals['earned']) }}</div>
        <div class="acct-stat-foot">Profit share + commission, finalized months</div>
    </div>
    <div class="acct-stat" style="--accent:#0284c7">
        <div class="acct-stat-label">Paid <i class="fas fa-hand-holding-usd"></i></div>
        <div class="acct-stat-value">৳{{ $L($totals['paid']) }}</div>
        <div class="acct-stat-foot">Handed over so far</div>
    </div>
    <div class="acct-stat acct-stat-hero">
        <div class="acct-stat-label">Still to pay <i class="fas fa-wallet"></i></div>
        <div class="acct-stat-value">৳{{ $L($totals['balance']) }}</div>
        <div class="acct-stat-foot">Across all partners</div>
    </div>
</div>

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-tie mr-1 text-primary"></i> Partners</h3>
        <span class="small {{ abs($sharesTotal - 100) > 0.0001 ? 'text-danger font-weight-bold' : 'text-muted' }}">
            Active shares: {{ $num($sharesTotal) }}{{ abs($sharesTotal - 100) > 0.0001 ? ' — should be 100' : '' }}
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table pt-table mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">Name</th>
                        <th class="pt-num">Share</th>
                        <th class="pt-num">Commission</th>
                        <th class="pt-num">Credited</th>
                        <th class="pt-num">Paid</th>
                        <th class="pt-num pr-3">To pay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($partners as $p)
                        @php $s = $sums[$p->id] ?? null; @endphp
                        <tr @class(['inactive' => ! $p->is_active])>
                            <td class="pl-3">
                                <span class="pt-avatar">{{ mb_substr($p->name, 0, 2) }}</span>
                                <a href="{{ route('accounts.partners.show', $p) }}" class="pt-name">{{ $p->name }}</a>
                                @unless ($p->is_active)<span class="badge badge-secondary ml-1">Inactive</span>@endunless
                            </td>
                            <td class="pt-num">{{ $p->isShareholder() ? $num($p->share) : '—' }}</td>
                            <td class="pt-num">{{ $p->isCommissionHolder() ? $num($p->commission_percent) . '%' : '—' }}</td>
                            <td class="pt-num">{{ $L($s?->earned) }}</td>
                            <td class="pt-num">{{ $L($s?->paid) }}</td>
                            <td class="pt-num pr-3 font-weight-bold {{ Dec::isNegative($s?->balance ?? 0) ? 'text-danger' : '' }}">{{ $L($s?->balance) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted">
        Share and commission % are used by Net Profit months that are not finalized yet; a finalized month keeps the ones it was finalized with.
    </div>
</div>

@if ($isAdmin)
    <div class="card acct-panel">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-user-plus mr-1 text-primary"></i> Add partner</h3></div>
        <form method="POST" action="{{ route('accounts.partners.store') }}" class="card-body form-row align-items-end">
            @csrf
            <div class="col-md-4 form-group"><label>Name</label><input type="text" name="name" class="form-control" maxlength="80" required></div>
            <div class="col-md-2 form-group"><label>Phone</label><input type="text" name="phone" class="form-control" maxlength="30"></div>
            <div class="col-md-2 form-group"><label>Share</label><input type="number" name="share" step="0.001" min="0" class="form-control" value="0"></div>
            <div class="col-md-2 form-group"><label>Commission %</label><input type="number" name="commission_percent" step="0.001" min="0" max="100" class="form-control" value="0"></div>
            <div class="col-md-2 form-group"><button class="btn btn-primary btn-block"><i class="fas fa-plus"></i> Add</button></div>
        </form>
    </div>
@endif

@stop
