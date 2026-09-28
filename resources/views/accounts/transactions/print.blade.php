<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt RCPT-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }} — Sunlit Network</title>

    @include('accounts.invoices.partials.print-styles')

    <style>
        .receipt-sheet { max-width: 520px; }

        table.receipt-table { width: 100%; border-collapse: collapse; }
        table.receipt-table td { padding: 0.6rem 0; border-bottom: 1px solid var(--border); }
        table.receipt-table tr:last-child td { border-bottom: none; }
        table.receipt-table td.label { color: var(--ink-soft); font-size: 0.85rem; }
        table.receipt-table td.value { font-weight: 600; text-align: right; }

        .receipt-amount { text-align: center; margin: 1.5rem 0; }
        .receipt-amount .amount { font-size: 2rem; font-weight: 700; color: var(--brand); }

        table.signature-row { width: 100%; margin-top: 3rem; border-collapse: collapse; }
        table.signature-row td { text-align: center; width: 50%; border: none; padding: 0.4rem 1rem 0; border-top: 1px solid var(--ink); font-size: 0.8rem; color: var(--ink-soft); }
    </style>
</head>
<body>

    <div class="print-actions">
        <button onclick="window.print()">Print Receipt</button>
    </div>

    <div class="invoice-sheet receipt-sheet">

        <table class="invoice-header">
            <tr>
                <td class="brand-cell">
                    <table class="brand">
                        <tr>
                            <td class="logo-cell"><img src="{{ asset('images/logo-icon.png') }}?v={{ filemtime(public_path('images/logo-icon.png')) }}" alt="Sunlit Network"></td>
                            <td>
                                <p>Sunlit Network DC</p>
                                <p>Navaron, Sharsha, Jashore</p>
                                <p>Email: account@sunlitnetwork.com</p>
                                <p>Website: sunlitnetwork.com</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="meta-cell">
                    <div class="invoice-meta">
                        <h2>RECEIPT</h2>
                        <p>RCPT-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</p>
                        <p>{{ $transaction->transaction_date->format('d M, Y') }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <div class="receipt-amount">
            <p class="label" style="margin:0;">Amount Received</p>
            <p class="amount">৳{{ number_format($transaction->amount, 2) }}</p>
        </div>

        <table class="receipt-table">
            <tr>
                <td class="label">Received From</td>
                <td class="value">{{ $transaction->invoice->customer->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Category</td>
                <td class="value">{{ $transaction->category->name }}</td>
            </tr>
            <tr>
                <td class="label">Reference</td>
                <td class="value">{{ $transaction->invoice->invoice_number ?? $transaction->description ?? '—' }}</td>
            </tr>
            @if($transaction->description)
                <tr>
                    <td class="label">Description</td>
                    <td class="value">{{ $transaction->description }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Received By</td>
                <td class="value">{{ $transaction->recordedBy->name ?? '—' }}</td>
            </tr>
        </table>

        <table class="signature-row">
            <tr>
                <td>Received By</td>
                <td>Payer Signature</td>
            </tr>
        </table>

        <div class="footer-note">
            This is a computer-generated receipt from Sunlit Network ERP.
            @php($kamCustomer = $transaction->invoice?->customer)
            @if($kamCustomer && ($kamCustomer->kam_name || $kamCustomer->kam_phone))
                For any billing queries, please contact with your KAM
                @if($kamCustomer->kam_name)
                    <strong>{{ $kamCustomer->kam_name }}</strong>
                @endif
                @if($kamCustomer->kam_phone)
                    ({{ $kamCustomer->kam_phone }})
                @endif.
            @else
                For any billing queries, please contact with your KAM.
            @endif
        </div>

    </div>

</body>
</html>
