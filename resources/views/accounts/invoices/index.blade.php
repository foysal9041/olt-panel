@extends('adminlte::page')

@section('title', 'Invoices')

@section('content_header')
<h1>Invoices</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-file-invoice"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Invoiced</span>
                <span class="info-box-number">&#2547;{{ number_format($totalInvoiced, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Collected</span>
                <span class="info-box-number">&#2547;{{ number_format($totalCollected, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Outstanding</span>
                <span class="info-box-number">&#2547;{{ number_format($totalOutstanding, 2) }}</span>
            </div>
        </div>
    </div>

</div>

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">Generate Invoices</h3>
    </div>

    <div class="card-body">

        <form method="POST" action="{{ route('accounts.invoices.generate') }}" class="form-inline">
            @csrf

            <label class="mr-2">Billing Month</label>
            <input type="month" name="month" class="form-control mr-2" value="{{ $month->format('Y-m') }}" required>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-file-invoice-dollar"></i> Generate Invoices
            </button>
        </form>

        <small class="text-muted d-block mt-2">
            Mac Clients are billed their package rate (or the package's list price if no custom rate is set).
            Bandwidth Clients are billed the sum of their bandwidth rates, itemized per type.
            Customers who already have an invoice for the selected month, or have nothing to bill, are skipped — safe to click more than once.
        </small>

    </div>

</div>

<div class="card card-outline card-secondary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Invoices — {{ $month->format('F Y') }}</h3>

        @if($invoices->isNotEmpty())
            <a href="{{ route('accounts.invoices.print-batch', ['month' => $month->format('Y-m'), 'status' => request('status')]) }}"
               target="_blank" class="btn btn-info btn-sm">
                <i class="fas fa-print"></i> Print All ({{ $invoices->count() }})
            </a>
        @endif
    </div>

    <div class="card-body">

        <form method="GET" action="{{ route('accounts.invoices.index') }}" class="form-inline mb-3">

            <label class="mr-2">Month</label>
            <input type="month" name="month" class="form-control mr-2" value="{{ $month->format('Y-m') }}">

            <label class="mr-2">Status</label>
            <select name="status" class="form-control mr-2">
                <option value="">All</option>
                <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                <option value="partially_paid" {{ request('status') == 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
            </select>

            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-filter"></i> Filter
            </button>

        </form>

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th style="width: 12%">Invoice #</th>
                    <th style="width: 20%">Customer</th>
                    <th style="width: 18%">Product</th>
                    <th style="width: 15%" class="text-right">Amount</th>
                    <th style="width: 15%" class="text-center">Status</th>
                    <th style="width: 220px" class="text-center">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($invoices as $invoice)

                    <tr>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td>
                            <a href="{{ route('accounts.customers.show', $invoice->customer_id) }}">
                                {{ $invoice->customer->name }}
                            </a>
                        </td>
                        <td>
                            @if($invoice->product)
                                {{ $invoice->product->name }}
                            @elseif($invoice->items->isNotEmpty())
                                Bandwidth ({{ $invoice->items->count() }} type{{ $invoice->items->count() == 1 ? '' : 's' }})
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-right">&#2547;{{ number_format($invoice->amount, 2) }}</td>
                        <td class="text-center">
                            @if($invoice->isPaid())
                                <span class="badge badge-success">PAID</span>
                            @elseif($invoice->isPartiallyPaid())
                                <span class="badge badge-warning">
                                    PARTIALLY PAID — Due &#2547;{{ number_format($invoice->remainingDue(), 2) }}
                                </span>
                            @else
                                <span class="badge badge-danger">UNPAID</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('accounts.invoices.print', $invoice->id) }}" target="_blank" class="btn btn-info btn-sm">
                                <i class="fas fa-print"></i> Print
                            </a>

                            @unless($invoice->isPaid())
                                <form action="{{ route('accounts.invoices.mark-paid', $invoice->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        Mark Paid in Full
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="6" class="text-center">No invoices for this month yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
