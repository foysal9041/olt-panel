@extends('adminlte::page')

@section('title', 'Zone Settlement')

@section('content_header')
<x-accounts.header title="Zone Settlement" icon="fas fa-file-excel"
    subtitle="Upload each zone's Total Payment and Deduction — invoices, bKash charge and company income are worked out for you" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-5">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-upload mr-1 text-primary"></i> New settlement</h3></div>
            <form method="POST" action="{{ route('accounts.settlements.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    <div class="form-group">
                        <label>Excel file</label>
                        <div class="custom-file">
                            <input type="file" name="file" class="custom-file-input" id="settle-file" accept=".xlsx,.xls,.csv" required>
                            <label class="custom-file-label" for="settle-file">Choose .xlsx, .xls or .csv</label>
                        </div>
                        <small class="form-text text-muted">One row per zone with its name, Total Payment and Deduction (negative). Nothing in the file is changed.</small>
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label>Billing month</label>
                            <input type="month" name="month" value="{{ old('month') }}" class="form-control">
                            <small class="form-text text-muted">Blank: read from the sheet.</small>
                        </div>
                        <div class="col-6 form-group">
                            <label>Invoice date</label>
                            <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" class="form-control">
                        </div>
                        <div class="col-6 form-group">
                            <label>bKash charge</label>
                            <div class="input-group">
                                <input type="number" name="bkash_percent" value="{{ old('bkash_percent', '1.5') }}" step="0.001" min="0" max="10" class="form-control" required>
                                <div class="input-group-append"><span class="input-group-text">%</span></div>
                            </div>
                            <small class="form-text text-muted">Of the Total Payment.</small>
                        </div>
                        <div class="col-6 form-group">
                            <label>Blank Deduction</label>
                            <div class="custom-control custom-checkbox mt-1">
                                <input type="checkbox" class="custom-control-input" id="blank_as_zero" name="blank_as_zero" value="1" @checked(old('blank_as_zero'))>
                                <label class="custom-control-label small" for="blank_as_zero">Count as 0 (else the row is flagged and left out)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-search"></i> Read &amp; check</button>
                    <span class="small text-muted ml-2">You'll see every row before anything is saved.</span>
                </div>
            </form>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-calculator mr-1 text-primary"></i> How it's worked out</h3></div>
            <div class="card-body small">
                <table class="table table-sm mb-0">
                    <tr><th>Customer Invoice</th><td>= Total Payment + Deduction <span class="text-muted">(Deduction is already negative)</span></td></tr>
                    <tr><th>Payment Difference</th><td>= Total Payment − Customer Invoice</td></tr>
                    <tr><th>bKash Charge</th><td>= Total Payment × 1.5%</td></tr>
                    <tr><th>Company Income</th><td>= Payment Difference − bKash Charge</td></tr>
                </table>
                <p class="text-muted mb-0 mt-2">Example: ৳100,000 + (−৳10,000) = ৳90,000 invoice · difference ৳10,000 · bKash ৳1,500 · income ৳8,500.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Settlements</h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="pl-3">Month</th>
                                <th class="text-center">Zones</th>
                                <th class="text-right">Total Payment</th>
                                <th class="text-right">Company Income</th>
                                <th class="text-center">Invoices</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($settlements as $s)
                                @php
                                    $t = $s->totals();
                                    $inv = $s->rows->where('included', true)->pluck('invoice_no')->filter()->sort(SORT_NATURAL)->values();
                                    $flags = $s->rows->filter(fn ($r) => ! empty($r->flags))->count();
                                @endphp
                                <tr>
                                    <td class="pl-3">
                                        <a href="{{ route('accounts.settlements.show', $s) }}" class="font-weight-bold">{{ $s->month->format('F Y') }}</a>
                                        <div class="small text-muted">{{ $s->source_name }} · {{ $s->creator?->name ?? '—' }}, {{ $s->created_at->format('d M') }}</div>
                                    </td>
                                    <td class="text-center">{{ $t['count'] }} @if ($flags)<span class="badge badge-warning" title="Rows to check">{{ $flags }}</span>@endif</td>
                                    <td class="text-right money">{{ \App\Support\Dec::taka($t['payment']) }}</td>
                                    <td class="text-right money font-weight-bold">{{ \App\Support\Dec::taka($t['income']) }}</td>
                                    <td class="text-center small text-muted">{{ $inv->first() }}@if ($inv->count() > 1) – {{ \Illuminate\Support\Str::afterLast($inv->last(), '/') }}@endif</td>
                                    <td class="text-right pr-3"><a href="{{ route('accounts.settlements.show', $s) }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-right"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="acct-empty"><i class="fas fa-file-excel"></i>No settlements yet — upload the month's Excel on the left.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('settle-file').addEventListener('change', function () {
    this.nextElementSibling.textContent = this.files[0] ? this.files[0].name : 'Choose .xlsx, .xls or .csv';
});
</script>

@stop
