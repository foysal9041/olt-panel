<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $number = 'RCPT-' . str_pad($payments->first()->id, 6, '0', STR_PAD_LEFT);
        $total = $payments->sum('amount');
        $first = $payments->first();
    @endphp
    <title>Receipt {{ $number }} — Sunlit Network</title>

    @include('accounts.invoices.partials.print-styles')

    <style>
        .receipt-sheet { max-width: 620px; }
        .receipt-amount { text-align: center; margin: 1.5rem 0 1rem; }
        .receipt-amount .amount { font-size: 2.1rem; font-weight: 700; color: var(--brand); margin: 0; }
        table.receipt-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        table.receipt-table td { padding: .5rem 0; border-bottom: 1px solid var(--border); }
        table.receipt-table td.label { color: var(--ink-soft); font-size: .85rem; }
        table.receipt-table td.value { font-weight: 600; text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; font-size: .85rem; }
        table.lines th { text-align: left; padding: .45rem .4rem; border-bottom: 2px solid var(--ink); font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
        table.lines td { padding: .45rem .4rem; border-bottom: 1px solid var(--border); }
        table.lines .r { text-align: right; font-variant-numeric: tabular-nums; }
        table.lines tfoot td { font-weight: 700; border-bottom: none; border-top: 2px solid var(--ink); }
        .balance { margin-top: 1rem; padding: .7rem .9rem; border-radius: .5rem; background: #f8fafc; display: flex; justify-content: space-between; font-weight: 600; }
        table.signature-row { width: 100%; margin-top: 3rem; border-collapse: collapse; }
        table.signature-row td { text-align: center; width: 50%; border: none; padding: .4rem 1rem 0; border-top: 1px solid var(--ink); font-size: .8rem; color: var(--ink-soft); }
        .screen-links { max-width: 620px; margin: 0 auto 1rem; display: flex; gap: .5rem; justify-content: center; font-family: system-ui, sans-serif; font-size: .85rem; }
        .screen-links a { color: #4f46e5; text-decoration: none; padding: .4rem .8rem; border: 1px solid #c7d2fe; border-radius: .4rem; background: #fff; }
        @media print { .screen-links { display: none; } }
    </style>
</head>
<body>

    <div class="print-actions">
        <button onclick="window.print()">Print Receipt</button>
    </div>

    <div class="screen-links">
        <a href="{{ route('accounts.payments.create') }}">+ Receive another payment</a>
        <a href="{{ route('accounts.customers.show', $customer) }}">{{ $customer->name }}</a>
        <a href="{{ route('accounts.customers.ledger', $customer) }}">Ledger</a>
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
                        <p>{{ $number }}</p>
                        <p>{{ $first->transaction_date->format('d M, Y') }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <div class="receipt-amount">
            <p class="label" style="margin:0;">Amount Received</p>
            <p class="amount">৳{{ number_format($total, 2) }}</p>
        </div>

        <table class="receipt-table">
            <tr>
                <td class="label">Received From</td>
                <td class="value">{{ $customer->name }}{{ $customer->phone ? ' · ' . $customer->phone : '' }}</td>
            </tr>
            @php $how = \Illuminate\Support\Str::of($first->description)->after('(')->beforeLast(')'); @endphp
            @if (str_contains((string) $first->description, '('))
                <tr>
                    <td class="label">Payment</td>
                    <td class="value">{{ $how }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Received By</td>
                <td class="value">{{ $first->recordedBy->name ?? '—' }}</td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Month</th>
                    <th class="r">Bill</th>
                    <th class="r">Paid now</th>
                    <th class="r">Still due</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $p)
                    <tr>
                        <td>{{ $p->invoice->invoice_number }}</td>
                        <td>{{ $p->invoice->billing_month?->format('M Y') }}</td>
                        <td class="r">৳{{ number_format($p->invoice->amount, 2) }}</td>
                        <td class="r">৳{{ number_format($p->amount, 2) }}</td>
                        <td class="r">{{ $p->invoice->remainingDue() > 0 ? '৳' . number_format($p->invoice->remainingDue(), 2) : 'Paid' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total received</td>
                    <td class="r">৳{{ number_format($total, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="balance">
            <span>Balance still due</span>
            <span>{{ $remaining > 0 ? '৳' . number_format($remaining, 2) : 'Nil — fully paid' }}</span>
        </div>

        <table class="signature-row">
            <tr>
                <td>Received By</td>
                <td>Payer Signature</td>
            </tr>
        </table>

        <div class="footer-note">
            This is a computer-generated receipt from Sunlit Network ERP.
            @if($customer->kam_name || $customer->kam_phone)
                For any billing queries, please contact with your KAM
                @if($customer->kam_name) <strong>{{ $customer->kam_name }}</strong> @endif
                @if($customer->kam_phone) ({{ $customer->kam_phone }}) @endif.
            @else
                For any billing queries, please contact with your KAM.
            @endif
        </div>

    </div>

</body>
</html>
