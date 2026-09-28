@extends('adminlte::page')

@section('title', 'Customer Details')

@section('content_header')
<h1>Customer Details</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card card-outline card-info">

    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-user mr-1"></i>
            {{ $customer->name }}
            @if($customer->customer_type == 'corporate_client')
                <span class="badge badge-primary ml-2">{{ $customer->customerTypeLabel() }}</span>
            @elseif($customer->isBandwidthClient())
                <span class="badge badge-info ml-2">{{ $customer->customerTypeLabel() }}</span>
            @else
                <span class="badge badge-secondary ml-2">{{ $customer->customerTypeLabel() }}</span>
            @endif
        </h3>
    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">

                <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Contact</h6>

                <table class="table table-borderless table-sm">
                    <tr>
                        <th width="140">Phone</th>
                        <td>{{ $customer->phone ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td>{{ $customer->address ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Zone</th>
                        <td>{{ $customer->zone ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            @if($customer->status)
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-danger">INACTIVE</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Outstanding Due</th>
                        <td>
                            @php($due = $customer->outstandingDue())
                            @if($due > 0)
                                <span class="badge badge-danger">&#2547;{{ number_format($due, 2) }}</span>
                            @else
                                <span class="text-muted">None</span>
                            @endif
                        </td>
                    </tr>
                </table>

                @if($customer->isBandwidthClient())

                    <h6 class="text-muted text-uppercase small font-weight-bold mb-3 mt-4">Key Account Manager</h6>

                    <table class="table table-borderless table-sm">
                        <tr>
                            <th width="140">Name</th>
                            <td>{{ $customer->kam_name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td>{{ $customer->kam_phone ?? '—' }}</td>
                        </tr>
                    </table>

                @endif

            </div>

            <div class="col-md-6">

                @if($customer->usesPackage())

                    <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Package</h6>

                    @if($customer->product)
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th width="140">Package</th>
                                <td>{{ $customer->product->name }}</td>
                            </tr>
                            <tr>
                                <th>List Price</th>
                                <td>&#2547;{{ number_format($customer->product->price, 2) }}</td>
                            </tr>
                            <tr>
                                <th>Billed Rate</th>
                                <td>&#2547;{{ number_format($customer->package_rate ?? $customer->product->price, 2) }}</td>
                            </tr>
                        </table>
                    @else
                        <p class="text-muted">No package assigned.</p>
                    @endif

                @endif

                @if($customer->isBandwidthClient())

                    <h6 class="text-muted text-uppercase small font-weight-bold mb-3 mt-4">Bandwidth Rates</h6>

                    @if($customer->bandwidthRates->isEmpty())

                        <p class="text-muted">No bandwidth rates set for this customer.</p>

                    @else

                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th class="text-right">Rate (&#2547;/Mbps)</th>
                                    <th class="text-right">Quantity (Mbps)</th>
                                    <th class="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->bandwidthRates as $rate)
                                    <tr>
                                        <td>{{ $rate->bandwidthType->name }}</td>
                                        <td class="text-right">&#2547;{{ number_format($rate->rate, 2) }}</td>
                                        <td class="text-right">{{ number_format($rate->quantity, 2) }}</td>
                                        <td class="text-right">&#2547;{{ number_format($rate->lineTotal(), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="3" class="text-right">Total / month</td>
                                    <td class="text-right">&#2547;{{ number_format($customer->bandwidthRatesTotal(), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                    @endif

                @endif

            </div>

        </div>

    </div>

    <div class="card-footer">

        <a href="{{ route('accounts.customers.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to List
        </a>

        <a href="{{ route('accounts.customers.edit', $customer->id) }}" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit Customer
        </a>

        <a href="{{ route('accounts.customers.ledger', $customer->id) }}" class="btn btn-info">
            <i class="fas fa-book"></i>
            View Ledger
        </a>

        <form action="{{ route('accounts.customers.invoices.generate', $customer->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="fas fa-file-invoice"></i>
                Generate This Month's Invoice
            </button>
        </form>

    </div>

</div>

@include('accounts.customers.partials.invoice-history')

@php($outstandingInvoices = $invoices->whereIn('status', ['unpaid', 'partially_paid']))

<div class="card card-outline card-success">

    <div class="card-header">
        <h3 class="card-title">Record Payment (Money Received)</h3>
    </div>

    <div class="card-body">

        @if($outstandingInvoices->isEmpty())

            <p class="text-muted mb-0">No outstanding invoices to record a payment against.</p>

        @else

            <form action="{{ route('accounts.customers.payments.store', $customer->id) }}" method="POST" id="record-payment-form">
                @csrf

                <div class="form-row">

                    <div class="col-md-4 form-group">
                        <label>Invoice</label>
                        <select name="invoice_id" id="payment-invoice-select" class="form-control" required>
                            @foreach($outstandingInvoices as $invoice)
                                <option value="{{ $invoice->id }}" data-due="{{ $invoice->remainingDue() }}">
                                    {{ $invoice->invoice_number }} — {{ $invoice->billing_month->format('F Y') }} — Due &#2547;{{ number_format($invoice->remainingDue(), 2) }}
                                    @if($invoice->isPartiallyPaid())
                                        (partially paid)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Amount Received (&#2547;)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="payment-amount-input" class="form-control" required>
                        <small class="text-muted">Up to the amount still due — leave at max to settle the invoice in full.</small>
                    </div>

                    <div class="col-md-2 form-group">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Note (optional)</label>
                        <input type="text" name="note" class="form-control" placeholder="e.g. Paid via bKash">
                    </div>

                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-money-bill-wave"></i>
                    Record Payment &amp; Print Receipt
                </button>

            </form>

            <script>
            (function () {
                var select = document.getElementById('payment-invoice-select');
                var amountInput = document.getElementById('payment-amount-input');

                function syncAmount() {
                    var due = select.options[select.selectedIndex].dataset.due;
                    amountInput.max = due;
                    amountInput.value = due;
                }

                select.addEventListener('change', syncAmount);
                syncAmount();
            })();
            </script>

        @endif

    </div>

</div>

@stop
