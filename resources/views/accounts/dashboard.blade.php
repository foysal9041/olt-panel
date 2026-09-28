@extends('adminlte::page')

@section('title', 'Accounts Dashboard')

@section('content_header')
<h1>Accounts &amp; Billing Dashboard</h1>
@stop

@section('content')

<div class="row">

    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-arrow-down"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">This Month Income</span>
                <span class="info-box-number">&#2547;{{ number_format($monthlyIncome, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-arrow-up"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">This Month Expense</span>
                <span class="info-box-number">&#2547;{{ number_format($monthlyExpense, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Active Customers</span>
                <span class="info-box-number">{{ $activeCustomers }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-secondary"><i class="fas fa-box"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Active Products</span>
                <span class="info-box-number">{{ $activeProducts }}</span>
            </div>
        </div>
    </div>

</div>

<div class="row">

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-file-invoice"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">This Month Invoiced</span>
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

<div class="row">

    <div class="col-lg-8">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Last 6 Months — Income vs Expense</h3>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 280px;">
                    <canvas id="incomeExpenseTrend"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">Quick Links</h3>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="{{ route('accounts.invoices.index') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-invoice-dollar mr-2"></i> Invoices
                    </a>
                    <a href="{{ route('accounts.transactions.index') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-money-bill-wave mr-2"></i> Income &amp; Expenses
                    </a>
                    <a href="{{ route('accounts.customers.index') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-users mr-2"></i> Customers
                    </a>
                    <a href="{{ route('accounts.products.index') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-box mr-2"></i> Products
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

@stop

@section('js')
<script>
new Chart(document.getElementById('incomeExpenseTrend').getContext('2d'), {
    type: 'line',
    data: {
        labels: [@foreach($trend as $row)'{{ $row['label'] }}',@endforeach],
        datasets: [
            {
                label: 'Income',
                data: [@foreach($trend as $row){{ $row['income'] }},@endforeach],
                borderColor: '#0ca30c',
                backgroundColor: 'rgba(12,163,12,0.08)',
                pointBackgroundColor: '#0ca30c',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.25,
                fill: true,
            },
            {
                label: 'Expense',
                data: [@foreach($trend as $row){{ $row['expense'] }},@endforeach],
                borderColor: '#d03b3b',
                backgroundColor: 'rgba(208,59,59,0.08)',
                pointBackgroundColor: '#d03b3b',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.25,
                fill: true,
            },
        ],
    },
    options: {
        maintainAspectRatio: false,
        legend: {
            display: true,
            position: 'bottom',
        },
        scales: {
            yAxes: [{
                ticks: { beginAtZero: true },
                gridLines: { color: '#e1e0d9' },
            }],
            xAxes: [{
                gridLines: { display: false },
            }],
        },
    },
});
</script>
@stop
