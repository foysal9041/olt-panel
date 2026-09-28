    <div class="invoice-sheet">

        <div class="head">
            <div class="brand">
                <img src="{{ asset('images/logo-icon.png') }}?v={{ filemtime(public_path('images/logo-icon.png')) }}" alt="Sunlit Network">
                <div>
                    <div class="company">Sunlit Network DC</div>
                    <div class="company-meta">
                        Navaron, Sharsha, Jashore<br>
                        Email: account@sunlitnetwork.com | Website: sunlitnetwork.com
                    </div>
                </div>
            </div>
            <div class="titlebox">
                <div class="title">INVOICE</div>
                <div class="subid">{{ $invoice->invoice_number }}</div>
                <span class="status-badge {{ $invoice->isPaid() ? 'status-paid' : ($invoice->isPartiallyPaid() ? 'status-partial' : 'status-unpaid') }}">
                    {{ str_replace('_', ' ', strtoupper($invoice->status)) }}
                </span>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h4>Billed To</h4>
                <div class="kv">
                    <div class="k">Client</div><div>{{ $invoice->customer->name }}</div>
                    @if($invoice->customer->phone)
                        <div class="k">Phone</div><div>{{ $invoice->customer->phone }}</div>
                    @endif
                    @if($invoice->customer->address)
                        <div class="k">Address</div><div>{{ $invoice->customer->address }}</div>
                    @endif
                    @if($invoice->customer->zone)
                        <div class="k">Zone</div><div>{{ $invoice->customer->zone }}</div>
                    @endif
                    @if($invoice->customer->isBandwidthClient() && ($invoice->customer->kam_name || $invoice->customer->kam_phone))
                        <div class="k">Account Mgr</div>
                        <div>{{ trim(($invoice->customer->kam_name ?? '') . ' ' . ($invoice->customer->kam_phone ? '(' . $invoice->customer->kam_phone . ')' : '')) }}</div>
                    @endif
                </div>
            </div>
            <div class="info-card">
                <h4>Invoice Info</h4>
                <div class="kv">
                    <div class="k">Invoice No</div><div>{{ $invoice->invoice_number }}</div>
                    <div class="k">Billing Month</div><div>{{ $invoice->billing_month->format('F Y') }}</div>
                    <div class="k">Issued</div><div>{{ $invoice->issued_at?->format('d M, Y') ?? '-' }}</div>
                    <div class="k">Total</div><div>&#2547;{{ number_format($invoice->amount, 2) }}</div>
                    <div class="k">Paid</div><div>&#2547;{{ number_format($invoice->amountPaid(), 2) }}</div>
                    <div class="k">Due</div><div>&#2547;{{ number_format($invoice->remainingDue(), 2) }}</div>
                </div>
            </div>
        </div>

        <table class="items">
            <colgroup>
                <col style="width: 32%">
                <col style="width: 14%">
                <col style="width: 12%">
                <col style="width: 22%">
                <col style="width: 20%">
            </colgroup>
            <thead>
                <tr>
                    <th>Particulars</th>
                    <th class="amount">Rate</th>
                    <th class="amount">Mbps</th>
                    <th>Billing Period</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            @php
                $baseItems = $invoice->items->where('is_adjustment', false);
                $adjustments = $invoice->items->where('is_adjustment', true);
                $periodLabel = $invoice->billing_month->copy()->startOfMonth()->format('d M')
                    . ' – ' . $invoice->billing_month->copy()->endOfMonth()->format('d M, Y');
            @endphp
            <tbody>
                @if($baseItems->isNotEmpty())
                    @foreach($baseItems as $item)
                        <tr>
                            <td>{{ $item->label }}</td>
                            <td class="amount">{{ $item->rate !== null ? '৳' . number_format($item->rate, 2) : '—' }}</td>
                            <td class="amount">{{ $item->quantity !== null ? rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') : '—' }}</td>
                            <td>{{ $periodLabel }}</td>
                            <td class="amount">৳{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                @else
                    @php($fallbackAmount = $invoice->amount - $adjustments->sum('amount'))
                    <tr>
                        <td>{{ $invoice->product->name ?? 'Service' }}</td>
                        <td class="amount">৳{{ number_format($fallbackAmount, 2) }}</td>
                        <td class="amount">1</td>
                        <td>{{ $periodLabel }}</td>
                        <td class="amount">৳{{ number_format($fallbackAmount, 2) }}</td>
                    </tr>
                @endif
                @foreach($adjustments as $adjustment)
                    <tr class="adjustment-row">
                        <td>
                            {{ $adjustment->label }} <span class="adjustment-tag">Adjustment</span>
                            @if($adjustment->remark)
                                <div class="remark-note">{{ $adjustment->remark }}</div>
                            @endif
                        </td>
                        <td class="amount">—</td>
                        <td class="amount">—</td>
                        <td>{{ $periodLabel }}</td>
                        <td class="amount">
                            {{ $adjustment->amount >= 0 ? '+' : '&minus;' }}৳{{ number_format(abs($adjustment->amount), 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div class="payments-card">
                <h4>Payment History</h4>
                @if($invoice->payments->isEmpty())
                    <p class="locked-note">No payments recorded yet.</p>
                @else
                    <table class="payments-mini">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="amount">Amount</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->payments as $payment)
                                <tr>
                                    <td>{{ $payment->transaction_date->format('d M, Y') }}</td>
                                    <td class="amount">&#2547;{{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ $payment->recordedBy->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="totals-box">
                <table>
                    <tr>
                        <td class="label">Total Amount</td>
                        <td class="amount">&#2547;{{ number_format($invoice->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Paid Amount</td>
                        <td class="amount">&#2547;{{ number_format($invoice->amountPaid(), 2) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="label">Remaining Due</td>
                        <td class="amount">&#2547;{{ number_format($invoice->remainingDue(), 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="amount-in-words">
            In Words: {{ \App\Support\NumberToWords::taka((float) $invoice->amount) }}
        </p>

        @if($invoice->isPaid())
            <p style="color:#166534; font-weight:600;">
                Paid on {{ $invoice->paid_at?->format('d M, Y') }}. Thank you for your business.
            </p>
        @elseif($invoice->isPartiallyPaid())
            <p style="color:#92400e; font-weight:600;">
                &#2547;{{ number_format($invoice->amountPaid(), 2) }} received so far — &#2547;{{ number_format($invoice->remainingDue(), 2) }} still due.
            </p>
        @endif

        <div class="footer-note">
            This is a computer-generated invoice from Sunlit Network ERP.
            @if($invoice->customer->kam_name || $invoice->customer->kam_phone)
                For any billing queries, please contact with your KAM
                @if($invoice->customer->kam_name)
                    <strong>{{ $invoice->customer->kam_name }}</strong>
                @endif
                @if($invoice->customer->kam_phone)
                    ({{ $invoice->customer->kam_phone }})
                @endif.
            @else
                For any billing queries, please contact with your KAM.
            @endif
        </div>

    </div>
