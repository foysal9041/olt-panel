@extends('adminlte::page')

@section('title', 'Bandwidth Billing')

@php
    $money = fn ($v) => number_format((float) $v, 2);
    $ym = $month->format('Y-m');
@endphp

@section('content_header')
<x-accounts.header title="Bandwidth Billing" icon="fas fa-tachometer-alt" subtitle="Bandwidth clients' monthly invoices and payments — {{ $month->format('F Y') }}">
    @if ($invoices->isNotEmpty())
        <a href="{{ route('accounts.billing.print-month', ['month' => $ym]) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print all</a>
        <a href="{{ route('accounts.billing.print-month', ['month' => $ym, 'download' => 1]) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-pdf"></i> Download all (PDF)</a>
    @endif
</x-accounts.header>
@stop

@section('css')
<style>
    .bl-table th { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-top: 0; }
    .bl-table td, .bl-table th { vertical-align: middle; }
    .bl-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
</style>
@stop

@section('content')

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

<div class="d-flex align-items-center flex-wrap mb-3" style="gap:.6rem">
    <form method="GET" class="input-group input-group-sm" style="width:auto">
        <div class="input-group-prepend"><a href="{{ route('accounts.billing.index', ['month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-light border"><i class="fas fa-chevron-left"></i></a></div>
        <input type="month" name="month" value="{{ $ym }}" class="form-control" onchange="this.form.submit()" style="width:11rem">
        <div class="input-group-append"><a href="{{ route('accounts.billing.index', ['month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}" class="btn btn-light border"><i class="fas fa-chevron-right"></i></a></div>
    </form>
    @if ($closed)<span class="badge badge-success p-2"><i class="fas fa-lock"></i> {{ $month->format('F Y') }} closed in Net Profit</span>@endif
    <span class="small text-muted">Pick the month, then make each client's invoice below.</span>
</div>

<div class="acct-stats">
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">Billed · {{ $month->format('M') }} <i class="fas fa-file-invoice"></i></div>
        <div class="acct-stat-value">৳{{ $money($billed) }}</div>
        <div class="acct-stat-foot">{{ $invoices->count() }} {{ Str::plural('invoice', $invoices->count()) }}</div>
    </div>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Received · {{ $month->format('M') }} <i class="fas fa-hand-holding-usd"></i></div>
        <div class="acct-stat-value">৳{{ $money($collected) }}</div>
        <div class="acct-stat-foot">Payments dated this month</div>
    </div>
    <div class="acct-stat acct-stat-hero">
        <div class="acct-stat-label">Outstanding now <i class="fas fa-exclamation-circle"></i></div>
        <div class="acct-stat-value">৳{{ $money($outstanding) }}</div>
        <div class="acct-stat-foot">All bandwidth clients</div>
    </div>
</div>

@if (! $closed)
    <div class="card acct-panel">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus-circle mr-1 text-primary"></i> Make an invoice</h3>
            <span class="small text-muted">From the client's rates &amp; Mbps — the lines can be changed after</span>
        </div>
        <div class="card-body">
            @if ($missing->isEmpty())
                <div class="small text-muted"><i class="fas fa-check-circle text-success"></i> Every bandwidth client has a {{ $month->format('F Y') }} invoice.</div>
            @else
                <form method="POST" action="{{ route('accounts.billing.generate') }}" class="form-row align-items-end">
                    @csrf
                    <input type="hidden" name="month" value="{{ $ym }}">
                    <div class="col-md-5 form-group mb-2">
                        <label class="small font-weight-bold mb-0">Client</label>
                        <select name="customer_id" class="form-control" required>
                            @foreach ($missing as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}{{ $c->contact_person ? ' — ' . $c->contact_person : '' }}{{ $c->status ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label class="small font-weight-bold mb-0">Invoice date</label>
                        <input type="date" name="invoice_date" value="{{ now()->max($month)->min($month->copy()->endOfMonth())->toDateString() }}" class="form-control">
                    </div>
                    <div class="col-md-4 form-group mb-2">
                        <button class="btn btn-primary btn-block"><i class="fas fa-file-invoice"></i> Make {{ $month->format('F') }} invoice</button>
                    </div>
                </form>
                @if ($missing->where('status', true)->count() > 1)
                    <form method="POST" action="{{ route('accounts.billing.generate') }}" class="mt-1">
                        @csrf
                        <input type="hidden" name="month" value="{{ $ym }}">
                        <button class="btn btn-link btn-sm p-0"><i class="fas fa-magic"></i> Make for all {{ $missing->where('status', true)->count() }} active clients at once</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endif

<div class="card acct-panel">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1 text-primary"></i> {{ $month->format('F Y') }} invoices</h3>
        <span class="small text-muted">Total MRC = Total Bill + previous due</span>
    </div>
    <div class="card-body p-0">
        @if ($invoices->isEmpty())
            <div class="acct-empty"><i class="fas fa-file-invoice"></i>No invoices for {{ $month->format('F Y') }} yet.</div>
        @else
            <div class="table-responsive">
                <table class="table bl-table mb-0">
                    <thead>
                        <tr>
                            <th class="pl-3">Invoice</th><th>Client</th>
                            <th class="bl-num">Total Bill</th><th class="bl-num">Prev. due</th><th class="bl-num">Total MRC</th>
                            <th class="bl-num">Paid</th><th class="bl-num">Due</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $inv)
                            @php $f = $inv->figures; $due = (float) $f['due']; @endphp
                            <tr>
                                <td class="pl-3 text-nowrap"><a href="{{ route('accounts.billing.show', $inv) }}" class="font-weight-bold">{{ $inv->invoice_no }}</a></td>
                                <td>
                                    <a href="{{ route('accounts.customers.show', $inv->customer) }}" style="color:#0f172a">{{ $inv->customer->name }}</a>
                                    @if ($inv->customer->contact_person)<div class="small text-muted">{{ $inv->customer->contact_person }}</div>@endif
                                </td>
                                <td class="bl-num">{{ $money($f['bill']) }}</td>
                                <td class="bl-num {{ (float) $f['previous'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $money($f['previous']) }}</td>
                                <td class="bl-num font-weight-bold">{{ $money($f['mrc']) }}</td>
                                <td class="bl-num text-income">{{ $money($f['paid']) }}</td>
                                <td class="bl-num font-weight-bold">
                                    @if ($due <= 0)
                                        <span class="badge badge-success">Paid</span>
                                    @else
                                        <span class="text-danger">{{ $money($f['due']) }}</span>
                                    @endif
                                </td>
                                <td class="text-right pr-3 text-nowrap">
                                    <a href="{{ route('accounts.billing.print', $inv) }}" target="_blank" class="btn btn-light btn-sm" title="Print"><i class="fas fa-print"></i></a>
                                    <a href="{{ route('accounts.billing.print', [$inv, 'download' => 1]) }}" target="_blank" class="btn btn-light btn-sm text-success" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                                    <a href="{{ route('accounts.billing.show', $inv) }}" class="btn btn-light btn-sm" title="Open"><i class="fas fa-arrow-right"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@stop
