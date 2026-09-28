<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ledger — {{ $customer->name }} — Sunlit Network</title>

    @include('accounts.invoices.partials.print-styles')

    <style>
        table.items td.debit, table.items th.debit,
        table.items td.credit, table.items th.credit,
        table.items td.balance, table.items th.balance { text-align: right; }
        table.items td.credit { color: #166534; }
        .summary-row td { border-bottom: none; padding-top: 1rem; font-weight: 700; }
    </style>
</head>
<body>

    <div class="print-actions">
        <button onclick="window.print()">Print Ledger</button>
    </div>

    <div class="invoice-sheet">

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
                        <h2>LEDGER</h2>
                        <p>As of {{ now()->format('d M, Y') }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="bill-to-row">
            <tr>
                <td>
                    <div class="bill-to">
                        <h3>Customer</h3>
                        <p><strong>{{ $customer->name }}</strong></p>
                        @if($customer->phone)
                            <p>{{ $customer->phone }}</p>
                        @endif
                        @if($customer->address)
                            <p>{{ $customer->address }}</p>
                        @endif
                        @if($customer->zone)
                            <p>Zone: {{ $customer->zone }}</p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th class="debit">Invoiced</th>
                    <th class="credit">Received</th>
                    <th class="balance">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('d M, Y') }}</td>
                        <td>{{ $entry['description'] }}</td>
                        <td class="debit">{{ $entry['debit'] > 0 ? '৳' . number_format($entry['debit'], 2) : '—' }}</td>
                        <td class="credit">{{ $entry['credit'] > 0 ? '৳' . number_format($entry['credit'], 2) : '—' }}</td>
                        <td class="balance">৳{{ number_format($entry['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No invoices on record for this customer yet.</td>
                    </tr>
                @endforelse

                @if($entries->isNotEmpty())
                    <tr class="summary-row">
                        <td></td>
                        <td>Total</td>
                        <td class="debit">৳{{ number_format($totalDebit, 2) }}</td>
                        <td class="credit">৳{{ number_format($totalCredit, 2) }}</td>
                        <td class="balance">৳{{ number_format($closingBalance, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <p style="{{ $closingBalance > 0 ? 'color:#991b1b; font-weight:600;' : 'color:#166534; font-weight:600;' }}">
            @if($closingBalance > 0)
                Outstanding balance due: ৳{{ number_format($closingBalance, 2) }}
            @else
                No outstanding balance.
            @endif
        </p>

        <div class="footer-note">
            This is a computer-generated ledger statement from Sunlit Network ERP.
            @if($customer->kam_name || $customer->kam_phone)
                For any billing queries, please contact with your KAM
                @if($customer->kam_name)
                    <strong>{{ $customer->kam_name }}</strong>
                @endif
                @if($customer->kam_phone)
                    ({{ $customer->kam_phone }})
                @endif.
            @else
                For any billing queries, please contact with your KAM.
            @endif
        </div>

    </div>

</body>
</html>
