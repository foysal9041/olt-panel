<div class="card card-outline card-secondary" id="invoice-history">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Invoice History</h3>
        @if($due = $customer->outstandingDue())
            <span class="badge badge-danger">&#2547;{{ number_format($due, 2) }} due</span>
        @endif
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped mb-0">
            <thead>
                <tr>
                    <th style="width: 15%">Invoice #</th>
                    <th style="width: 25%">Billing Month</th>
                    <th style="width: 20%" class="text-right">Amount</th>
                    <th style="width: 25%" class="text-center">Status</th>
                    <th style="width: 15%" class="text-center">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->invoice_number }}</td>
                        <td>{{ $invoice->billing_month->format('F Y') }}</td>
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
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No invoices yet for this customer</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>

</div>
