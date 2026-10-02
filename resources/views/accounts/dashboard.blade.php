@extends('adminlte::page')

@section('title', 'Accounts Dashboard')

@php
    $net = $income - $expense;
    $lastNet = $lastIncome - $lastExpense;
    $delta = fn ($now, $before) => $before == 0 ? null : round(($now - $before) / abs($before) * 100, 1);
    $pct = fn ($d) => abs($d) >= 1000 ? '999+%' : abs($d) . '%';
    $incomeDelta = $delta($income, $lastIncome);
    $expenseDelta = $delta($expense, $lastExpense);
    $tk = fn ($v, $d = 0) => '৳' . number_format((float) $v, $d);
    $signed = fn ($v) => ($v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v));
    $latest = $settlements->first();
@endphp

@section('content_header')
<x-accounts.header title="Accounts" icon="fas fa-chart-pie"
    subtitle="{{ $monthStart->format('F Y') }} at a glance — cash, income, expenses and settlements">
    @can('access-accounts-transactions')
        <a href="{{ route('accounts.transactions.create') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-plus"></i> Add Entry</a>
    @endcan
    @can('access-accounts-cashbook')
        <a href="{{ route('accounts.cashbook.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-book-open"></i> Today's Cash Book</a>
    @endcan
</x-accounts.header>
@stop

@section('content')

{{-- ============ Headline numbers ============ --}}
<div class="acct-stats">

    <div class="acct-stat acct-stat-hero">
        <div class="acct-stat-label">Cash in hand <i class="fas fa-wallet"></i></div>
        <div class="acct-stat-value {{ $cash['now'] < 0 ? 'text-warning' : '' }}">{{ $signed($cash['now']) }}</div>
        <div class="acct-stat-foot">
            Today <span class="hero-in">+{{ $tk($cash['in']) }}</span> · <span class="hero-out">−{{ $tk($cash['out']) }}</span>
            @unless ($cash['counted_on'])
                <div class="mt-1"><i class="fas fa-exclamation-circle"></i> Cash not counted yet</div>
            @endunless
        </div>
    </div>

    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Income · {{ $monthStart->format('M') }} <i class="fas fa-arrow-down"></i></div>
        <div class="acct-stat-value">{{ $tk($income) }}</div>
        <div class="acct-stat-foot">
            @if ($incomeDelta !== null)
                <span class="acct-pill {{ $incomeDelta >= 0 ? 'up' : 'down' }}"><i class="fas fa-caret-{{ $incomeDelta >= 0 ? 'up' : 'down' }}"></i> {{ $pct($incomeDelta) }}</span> vs {{ $tk($lastIncome) }} last month
            @else
                This month so far
            @endif
        </div>
    </div>

    <div class="acct-stat" style="--accent:#e11d48">
        <div class="acct-stat-label">Expense · {{ $monthStart->format('M') }} <i class="fas fa-arrow-up"></i></div>
        <div class="acct-stat-value">{{ $tk($expense) }}</div>
        <div class="acct-stat-foot">
            @if ($expenseDelta !== null)
                {{-- Rising expense is bad news, so the colours are flipped --}}
                <span class="acct-pill {{ $expenseDelta <= 0 ? 'up' : 'down' }}"><i class="fas fa-caret-{{ $expenseDelta >= 0 ? 'up' : 'down' }}"></i> {{ $pct($expenseDelta) }}</span> vs {{ $tk($lastExpense) }} last month
            @else
                This month so far
            @endif
        </div>
    </div>

    <div class="acct-stat" style="--accent:{{ $net >= 0 ? '#4f46e5' : '#e11d48' }}">
        <div class="acct-stat-label">Net · {{ $monthStart->format('M') }} <i class="fas fa-balance-scale"></i></div>
        <div class="acct-stat-value {{ $net < 0 ? 'text-expense' : '' }}">{{ $signed($net) }}</div>
        <div class="acct-stat-foot">Last month {{ $signed($lastNet) }}</div>
    </div>

</div>

<div class="row">

    {{-- ============ Trend ============ --}}
    <div class="col-xl-8">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-1 text-primary"></i> Income vs Expense <small class="text-muted">last 6 months</small></h3>
                @can('access-accounts-transactions')
                    <a href="{{ route('accounts.transactions.index') }}" class="small">All entries <i class="fas fa-arrow-right"></i></a>
                @endcan
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;"><canvas id="incomeExpenseTrend"></canvas></div>
            </div>
        </div>
    </div>

    {{-- ============ Zone settlement + salary ============ --}}
    <div class="col-xl-4">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-excel mr-1 text-success"></i> Zone Settlement</h3>
                @can('access-accounts-settlements')
                    <a href="{{ route('accounts.settlements.index') }}" class="small">All <i class="fas fa-arrow-right"></i></a>
                @endcan
            </div>
            @if ($latest)
                @php $s = $latest['model']; $t = $latest['totals']; @endphp
                <div class="card-body pb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="acct-kicker">{{ $s->month->format('F Y') }} · {{ $t['count'] }} zones</div>
                            <div class="acct-big text-income">{{ $tk($t['income'], 2) }}</div>
                            <div class="small text-muted">Net Bill (company income)</div>
                        </div>
                        @if ($s->isPosted())
                            <span class="badge badge-success"><i class="fas fa-check"></i> Posted</span>
                        @else
                            <span class="badge badge-warning"><i class="fas fa-clock"></i> Not posted</span>
                        @endif
                    </div>
                    <div class="acct-split mt-3">
                        <div><span>Total Payment</span><b>{{ $tk($t['payment']) }}</b></div>
                        <div><span>Total Payable</span><b>{{ $tk($t['invoice']) }}</b></div>
                        <div><span>bKash</span><b>{{ $tk($t['bkash']) }}</b></div>
                    </div>
                    @can('access-accounts-settlements')
                        <a href="{{ route('accounts.settlements.show', $s) }}" class="btn btn-sm btn-block mt-3 {{ $s->isPosted() ? 'btn-light border' : 'btn-success' }}">
                            {!! $s->isPosted() ? '<i class="fas fa-eye"></i> Open settlement' : '<i class="fas fa-check"></i> Review &amp; post income' !!}
                        </a>
                    @endcan
                </div>
                @if ($settlements->count() > 1)
                    <div class="border-top">
                        @foreach ($settlements->skip(1) as ['model' => $old, 'totals' => $ot])
                            <a href="{{ Gate::allows('access-accounts-settlements') ? route('accounts.settlements.show', $old) : '#' }}" class="acct-list-row py-2">
                                <span class="acct-list-main small">{{ $old->month->format('F Y') }}</span>
                                <span class="money small font-weight-bold">{{ $tk($ot['income']) }}</span>
                                <i class="fas {{ $old->isPosted() ? 'fa-check-circle text-success' : 'fa-clock text-warning' }}" title="{{ $old->isPosted() ? 'Posted' : 'Not posted' }}"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="acct-empty"><i class="fas fa-file-excel"></i>No settlement yet.
                    @can('access-accounts-settlements')
                        <div class="mt-2"><a href="{{ route('accounts.settlements.index') }}" class="btn btn-sm btn-success">Upload Excel</a></div>
                    @endcan
                </div>
            @endif
            <div class="card-footer small text-muted d-flex justify-content-between">
                <span><i class="fas fa-users mr-1"></i> Active customers</span>
                <span>{{ $customers['mac_client'] ?? 0 }} MAC · {{ $customers['bandwidth_client'] ?? 0 }} Bandwidth</span>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-body d-flex align-items-center" style="gap:.85rem">
                <span class="acct-tile" style="--accent:#d97706"><i class="fas fa-money-check-alt"></i></span>
                <div class="flex-grow-1" style="min-width:0">
                    <div class="acct-kicker">Salary · {{ $salary?->month?->format('F Y') ?? 'no sheet yet' }}</div>
                    @if ($salary)
                        <div class="font-weight-bold money" style="font-size:1.15rem">{{ $tk($salary->net_total) }}</div>
                        <div class="small text-muted">{{ $salary->items_count }} staff · {{ $salary->isPosted() ? 'posted to expenses' : 'not posted yet' }}</div>
                    @else
                        <div class="small text-muted">Make the month's salary sheet</div>
                    @endif
                </div>
                @can('access-accounts-salaries')
                    <a href="{{ route('accounts.salaries.index') }}" class="btn btn-sm btn-light border"><i class="fas fa-arrow-right"></i></a>
                @endcan
            </div>
        </div>
    </div>

</div>

<div class="row">

    {{-- ============ By head ============ --}}
    @foreach ([
        ['Income by head', $incomeByCategory, '#16a34a', 'fas fa-arrow-down', $income],
        ['Expense by head', $expenseByCategory, '#e11d48', 'fas fa-arrow-up', $expense],
    ] as [$title, $rows, $color, $icon, $total])
        <div class="col-lg-4 col-md-6">
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="{{ $icon }} mr-1" style="color: {{ $color }}"></i> {{ $title }}</h3>
                    <span class="small text-muted">{{ $monthStart->format('F') }}</span>
                </div>
                <div class="card-body">
                    @forelse ($rows as $row)
                        <div class="acct-bar-row">
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ $row->name }} <span class="text-muted small">× {{ $row->entries }}</span></span>
                                <strong class="money">{{ $tk($row->total) }}</strong>
                            </div>
                            <div class="acct-progress mt-0" style="--accent: {{ $color }}">
                                <span style="width: {{ $total > 0 ? round($row->total / $total * 100, 1) : 0 }}%"></span>
                            </div>
                        </div>
                    @empty
                        <div class="acct-empty"><i class="fas fa-chart-bar"></i>Nothing recorded this month.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach

    {{-- ============ Recent entries ============ --}}
    <div class="col-lg-4 col-md-12">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-receipt mr-1 text-primary"></i> Recent entries</h3>
                @can('access-accounts-transactions')
                    <a href="{{ route('accounts.transactions.index') }}" class="small">View all <i class="fas fa-arrow-right"></i></a>
                @endcan
            </div>
            <div class="card-body p-0">
                @forelse ($recent as $tx)
                    @php $isIncome = $tx->category?->type === 'income'; @endphp
                    <div class="acct-list-row">
                        <span class="acct-avatar" style="background: {{ $isIncome ? '#dcfce7' : '#ffe4e6' }}; color: {{ $isIncome ? '#16a34a' : '#e11d48' }}">
                            <i class="fas fa-arrow-{{ $isIncome ? 'down' : 'up' }}"></i>
                        </span>
                        <span class="acct-list-main">
                            <div class="acct-list-title">{{ $tx->description ?: ($tx->category?->name ?? 'Entry') }}</div>
                            <div class="acct-list-sub">{{ $tx->category?->name }} · {{ $tx->transaction_date?->format('d M Y') }}</div>
                        </span>
                        <span class="acct-list-amount {{ $isIncome ? 'text-income' : 'text-expense' }}">{{ $isIncome ? '+' : '−' }}{{ $tk($tx->amount) }}</span>
                    </div>
                @empty
                    <div class="acct-empty"><i class="fas fa-receipt"></i>No entries yet.</div>
                @endforelse
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
})();
</script>
@stop
