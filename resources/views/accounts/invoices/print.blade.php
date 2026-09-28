<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }} — Sunlit Network</title>

    @include('accounts.invoices.partials.print-styles')
</head>
<body>

    <div class="print-actions">
        <button onclick="window.print()">Print Invoice</button>
    </div>

    @if(session('success'))
        <p class="adjustments-panel" style="color:#166534; padding:0.75rem 2.5rem;">{{ session('success') }}</p>
    @endif

    @if(session('error'))
        <p class="adjustments-panel" style="color:#991b1b; padding:0.75rem 2.5rem;">{{ session('error') }}</p>
    @endif

    @include('accounts.invoices.partials.sheet', ['invoice' => $invoice])

    <div class="adjustments-panel">

        <h3>Payments Received</h3>

        @if($invoice->payments->isEmpty())

            <p class="locked-note">No payments recorded yet.</p>

        @else

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="amount">Amount</th>
                        <th>Recorded By</th>
                        @can('access-accounts-payments-edit')
                            <th class="actions"></th>
                        @endcan
                    </tr>
                </thead>
                @foreach($invoice->payments as $payment)
                    <tr id="payment-view-{{ $payment->id }}">
                        <td>{{ $payment->transaction_date->format('d M, Y') }}</td>
                        <td class="amount">&#2547;{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $payment->recordedBy->name ?? '—' }}</td>
                        @can('access-accounts-payments-edit')
                            <td class="actions">
                                <button type="button" class="btn-remove" style="background:#475569;"
                                        onclick="document.getElementById('payment-view-{{ $payment->id }}').style.display='none'; document.getElementById('payment-edit-{{ $payment->id }}').style.display='table-row';">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('accounts.invoices.payments.destroy', [$invoice->id, $payment->id]) }}"
                                      style="display:inline;" onsubmit="return confirm('Remove this payment? The invoice balance will be recalculated.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-remove">Remove</button>
                                </form>
                            </td>
                        @endcan
                    </tr>
                    @can('access-accounts-payments-edit')
                        <tr id="payment-edit-{{ $payment->id }}" style="display:none;">
                            <td colspan="4" style="border-bottom:1px solid var(--border);">
                                <form method="POST" action="{{ route('accounts.invoices.payments.update', [$invoice->id, $payment->id]) }}" class="add-adjustment">
                                    @csrf
                                    @method('PUT')
                                    <input type="date" name="payment_date" value="{{ $payment->transaction_date->format('Y-m-d') }}" required>
                                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ $payment->amount }}" required>
                                    <input type="text" name="note" placeholder="Remark (optional)">
                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endcan
                @endforeach
            </table>

        @endif

    </div>

    @if($previousDue > 0)
        <div class="adjustments-panel">

            <h3>Previous Due</h3>

            <p>
                {{ $invoice->customer->name }} has &#2547;{{ number_format($previousDue, 2) }} outstanding
                from other unpaid invoices.
            </p>

            @if($previousDueAlreadyAdded)
                <p class="locked-note">Already added to this invoice as an adjustment below.</p>
            @elseif($invoice->isPaid())
                <p class="locked-note">This invoice is paid — adjustments are locked.</p>
            @else
                <form method="POST" action="{{ route('accounts.invoices.adjustments.store', $invoice->id) }}">
                    @csrf
                    <input type="hidden" name="label" value="Previous Due (as of {{ now()->format('d M, Y') }})">
                    <input type="hidden" name="amount" value="{{ $previousDue }}">
                    <button type="submit">Add &#2547;{{ number_format($previousDue, 2) }} to This Invoice</button>
                </form>
            @endif

        </div>
    @endif

    <div class="adjustments-panel">

        <h3>Adjustments</h3>

        @php($adjustments = $invoice->items->where('is_adjustment', true))

        @if($adjustments->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount</th>
                        @unless($invoice->isPaid())
                            <th class="actions"></th>
                        @endunless
                    </tr>
                </thead>
                @foreach($adjustments as $adjustment)
                    <tr>
                        <td>
                            {{ $adjustment->label }}
                            @if($adjustment->remark)
                                <div class="remark-note">{{ $adjustment->remark }}</div>
                            @endif
                        </td>
                        <td class="amount">{{ $adjustment->amount >= 0 ? '+' : '−' }}&#2547;{{ number_format(abs($adjustment->amount), 2) }}</td>
                        @unless($invoice->isPaid())
                            <td class="actions">
                                <form method="POST" action="{{ route('accounts.invoices.adjustments.destroy', [$invoice->id, $adjustment->id]) }}"
                                      onsubmit="return confirm('Remove this adjustment?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-remove">Remove</button>
                                </form>
                            </td>
                        @endunless
                    </tr>
                @endforeach
            </table>
        @endif

        @if($invoice->isPaid())
            <p class="locked-note">This invoice is paid — adjustments are locked.</p>
        @else
            <form method="POST" action="{{ route('accounts.invoices.adjustments.store', $invoice->id) }}" class="add-adjustment">
                @csrf
                <input type="text" name="label" placeholder="e.g. Mid-month upgrade to 70 Mbps" required>
                <input type="number" step="0.01" name="amount" placeholder="Amount (+/-)" required>
                <input type="text" name="remark" placeholder="Remark (optional)">
                <button type="submit">Add Adjustment</button>
            </form>
            <p class="locked-note" style="margin-top:0.6rem;">
                Positive amount = extra charge. Negative amount = credit. No automatic proration — enter the amount you've worked out for the upgrade/downgrade.
            </p>
        @endif

    </div>

</body>
</html>
