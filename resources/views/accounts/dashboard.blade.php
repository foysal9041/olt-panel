@extends('adminlte::page')

@section('title', 'Accounts Dashboard')

@php
    $net = $monthlyIncome - $monthlyExpense;
    $lastNet = $lastMonthIncome - $lastMonthExpense;
    $delta = function ($now, $before) {
        if ($before == 0) return null;
        return round(($now - $before) / abs($before) * 100, 1);
    };
    $incomeDelta = $delta($monthlyIncome, $lastMonthIncome);
    $expenseDelta = $delta($monthlyExpense, $lastMonthExpense);
    $invoiceCount = array_sum($invoiceStatus);
    $maxExpense = max(1, (float) ($expenseByCategory->max('total') ?? 0));
    $tk = fn ($v, $d = 0) => '৳' . number_format((float) $v, $d);
@endphp

@section('content_header')
<x-accounts.header title="Accounts & Billing" icon="fas fa-chart-pie"
    subtitle="{{ $monthStart->format('F Y') }} overview — income, billing and receivables">
    @can('access-accounts-transactions')
        <a href="{{ route('accounts.transactions.create') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-plus"></i> Add Transaction
        </a>
    @endcan
    @can('access-accounts-invoices')
        <a href="{{ route('accounts.invoices.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-invoice-dollar"></i> Invoices
        </a>
        <a href="{{ route('accounts.payments.create') }}" class="btn btn-success btn-sm">
            <i class="fas fa-hand-holding-usd"></i> Receive Payment
        </a>
    @endcan
</x-accounts.header>
@stop

@section('content')

{{-- ============ Headline numbers ============ --}}
<div class="acct-stats">

    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Income <i class="fas fa-arrow-down"></i></div>
        <div class="acct-stat-value">{{ $tk($monthlyIncome) }}</div>
        <div class="acct-stat-foot">
            @if ($incomeDelta !== null)
                <span class="acct-delta {{ $incomeDelta >= 0 ? 'up' : 'down' }}">
                    <i class="fas fa-caret-{{ $incomeDelta >= 0 ? 'up' : 'down' }}"></i> {{ abs($incomeDelta) }}%
                </span> vs last month
            @else
                This month
            @endif
        </div>
    </div>

    <div class="acct-stat" style="--accent:#e11d48">
        <div class="acct-stat-label">Expense <i class="fas fa-arrow-up"></i></div>
        <div class="acct-stat-value">{{ $tk($monthlyExpense) }}</div>
        <div class="acct-stat-foot">
            @if ($expenseDelta !== null)
                {{-- Rising expense is bad news, so the colours are flipped --}}
                <span class="acct-delta {{ $expenseDelta <= 0 ? 'up' : 'down' }}">
                    <i class="fas fa-caret-{{ $expenseDelta >= 0 ? 'up' : 'down' }}"></i> {{ abs($expenseDelta) }}%
                </span> vs last month
            @else
                This month
            @endif
        </div>
    </div>

    <div class="acct-stat" style="--accent:{{ $net >= 0 ? '#4f46e5' : '#e11d48' }}">
        <div class="acct-stat-label">Net Profit <i class="fas fa-balance-scale"></i></div>
        <div class="acct-stat-value {{ $net < 0 ? 'text-expense' : '' }}">{{ $net < 0 ? '−' : '' }}{{ $tk(abs($net)) }}</div>
        <div class="acct-stat-foot">Last month {{ $lastNet < 0 ? '−' : '' }}{{ $tk(abs($lastNet)) }}</div>
    </div>

    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">Collection Rate <i class="fas fa-hand-holding-usd"></i></div>
        <div class="acct-stat-value">{{ $collectionRate }}%</div>
        <div class="acct-stat-foot">{{ $tk($totalCollected) }} of {{ $tk($totalInvoiced) }} billed</div>
        <div class="acct-progress"><span style="width: {{ min(100, $collectionRate) }}%"></span></div>
    </div>

    <div class="acct-stat" style="--accent:#0284c7">
        <div class="acct-stat-label">Total Receivable <i class="fas fa-file-invoice"></i></div>
        <div class="acct-stat-value">{{ $tk($totalReceivable) }}</div>
        <div class="acct-stat-foot">All open invoices · {{ $activeCustomers }} active customers</div>
    </div>

</div>

{{-- ============ Charts ============ --}}
<div class="row">

    <div class="col-lg-8">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">Income vs Expense — last 6 months</h3>
                @can('access-accounts-transactions')
                    <a href="{{ route('accounts.transactions.index') }}" class="small">All transactions</a>
                @endcan
            </div>
            <div class="card-body">
                <div style="position: relative; height: 290px;">
                    <canvas id="incomeExpenseTrend"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">{{ $monthStart->format('F') }} Invoices</h3>
                <span class="text-muted small">{{ $invoiceCount }} total</span>
            </div>
            <div class="card-body">
                @if ($invoiceCount)
                    <div style="position: relative; height: 180px;">
                        <canvas id="invoiceStatus"></canvas>
                    </div>
                    <div class="mt-3">
                        @foreach ([['Paid', 'paid', '#22c55e'], ['Partially paid', 'partially_paid', '#f59e0b'], ['Unpaid', 'unpaid', '#f43f5e']] as [$label, $key, $color])
                            <div class="d-flex justify-content-between align-items-center py-1" style="font-size:.88rem">
                                <span><i class="fas fa-circle mr-2" style="color:{{ $color }}; font-size:.6rem"></i>{{ $label }}</span>
                                <strong>{{ $invoiceStatus[$key] }}</strong>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2 mt-2" style="font-size:.88rem">
                        <span class="text-muted">Outstanding this month</span>
                        <strong class="text-expense money">{{ $tk($totalOutstanding) }}</strong>
                    </div>
                @else
                    <div class="acct-empty">
                        <i class="fas fa-file-invoice"></i>
                        No invoices for {{ $monthStart->format('F') }} yet.
                        @can('access-accounts-invoices')
                            <div class="mt-2"><a href="{{ route('accounts.invoices.index') }}" class="btn btn-sm btn-primary">Generate invoices</a></div>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ============ Lists ============ --}}
<div class="row">

    <div class="col-lg-4 col-md-6">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">Top Dues</h3>
                @can('access-accounts-customers')
                    <a href="{{ route('accounts.customers.index') }}" class="small">Customers</a>
                @endcan
            </div>
            <div class="card-body p-0">
                @forelse ($topDues as $row)
                    <a href="{{ Gate::allows('access-accounts-customers') ? route('accounts.customers.show', $row['customer']) : '#' }}" class="acct-list-row">
                        <span class="acct-avatar">{{ mb_substr($row['customer']->name, 0, 2) }}</span>
                        <span class="acct-list-main">
                            <div class="acct-list-title">{{ $row['customer']->name }}</div>
                            <div class="acct-list-sub">
                                {{ $row['invoices'] }} open {{ Str::plural('invoice', $row['invoices']) }}
                                @if ($row['oldest']) · since {{ \Illuminate\Support\Carbon::parse($row['oldest'])->format('M Y') }} @endif
                            </div>
                        </span>
                        <span class="acct-list-amount text-expense">{{ $tk($row['due']) }}</span>
                    </a>
                @empty
                    <div class="acct-empty"><i class="fas fa-check-circle" style="color:#22c55e"></i>No outstanding dues.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">Recent Transactions</h3>
                @can('access-accounts-transactions')
                    <a href="{{ route('accounts.transactions.index') }}" class="small">View all</a>
                @endcan
            </div>
            <div class="card-body p-0">
                @forelse ($recentTransactions as $tx)
                    @php $isIncome = $tx->category?->type === 'income'; @endphp
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background: {{ $isIncome ? '#dcfce7' : '#ffe4e6' }}; color: {{ $isIncome ? '#16a34a' : '#e11d48' }}">
                            <i class="fas fa-arrow-{{ $isIncome ? 'down' : 'up' }}"></i>
                        </span>
                        <span class="acct-list-main">
                            <div class="acct-list-title">{{ $tx->description ?: ($tx->category?->name ?? 'Transaction') }}</div>
                            <div class="acct-list-sub">{{ $tx->category?->name }} · {{ $tx->transaction_date?->format('d M Y') }}</div>
                        </span>
                        <span class="acct-list-amount {{ $isIncome ? 'text-income' : 'text-expense' }}">
                            {{ $isIncome ? '+' : '−' }}{{ $tk($tx->amount) }}
                        </span>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-receipt"></i>No transactions yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-12">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title">{{ $monthStart->format('F') }} Expenses by Category</h3>
            </div>
            <div class="card-body">
                @forelse ($expenseByCategory as $row)
                    <div class="acct-bar-row">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $row->name }}</span>
                            <strong class="money">{{ $tk($row->total) }}</strong>
                        </div>
                        <div class="acct-progress mt-0" style="--accent:#e11d48">
                            <span style="width: {{ round($row->total / $maxExpense * 100) }}%"></span>
                        </div>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-chart-bar"></i>No expenses recorded this month.</div>
                @endforelse

                <div class="d-flex justify-content-between border-top pt-3 mt-3 small text-muted">
                    <span><i class="fas fa-users mr-1"></i> {{ $activeCustomers }} active customers</span>
                    <span><i class="fas fa-box mr-1"></i> {{ $activeProducts }} active products</span>
                </div>
            </div>
        </div>
    </div>

</div>

@stop

@section('js')
<script>
(function () {
    var tk = function (v) { return '৳' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 }); };

    new Chart(document.getElementById('incomeExpenseTrend').getContext('2d'), {
        type: 'bar',
        data: {
            labels: @json(array_column($trend, 'label')),
            datasets: [
                { label: 'Income', data: @json(array_column($trend, 'income')), backgroundColor: '#22c55e', hoverBackgroundColor: '#16a34a' },
                { label: 'Expense', data: @json(array_column($trend, 'expense')), backgroundColor: '#fb7185', hoverBackgroundColor: '#e11d48' },
            ],
        },
        options: {
            maintainAspectRatio: false,
            legend: { display: true, position: 'bottom', labels: { boxWidth: 12, fontColor: '#475569' } },
            tooltips: {
                mode: 'index',
                intersect: false,
                callbacks: { label: function (item, data) { return data.datasets[item.datasetIndex].label + ': ' + tk(item.yLabel); } },
            },
            scales: {
                yAxes: [{
                    ticks: { beginAtZero: true, fontColor: '#94a3b8', callback: function (v) { return tk(v); } },
                    gridLines: { color: '#eef2f7', drawBorder: false },
                }],
                xAxes: [{
                    barPercentage: 0.7,
                    categoryPercentage: 0.6,
                    ticks: { fontColor: '#64748b' },
                    gridLines: { display: false },
                }],
            },
        },
    });

    var statusEl = document.getElementById('invoiceStatus');
    if (statusEl) {
        new Chart(statusEl.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Partially paid', 'Unpaid'],
                datasets: [{
                    data: [{{ $invoiceStatus['paid'] }}, {{ $invoiceStatus['partially_paid'] }}, {{ $invoiceStatus['unpaid'] }}],
                    backgroundColor: ['#22c55e', '#f59e0b', '#f43f5e'],
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutoutPercentage: 68,
                legend: { display: false },
            },
        });
    }
})();
</script>
@stop
