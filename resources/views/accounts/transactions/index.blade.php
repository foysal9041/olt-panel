@extends('adminlte::page')

@section('title', 'Transactions')

@section('content_header')
<x-accounts.header title="Income & Expenses" icon="fas fa-exchange-alt" subtitle="Every taka in and out, by category" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-arrow-down"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Income</span>
                <span class="info-box-number">&#2547;{{ number_format($totalIncome, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-arrow-up"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Expense</span>
                <span class="info-box-number">&#2547;{{ number_format($totalExpense, 2) }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-balance-scale"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Net</span>
                <span class="info-box-number">&#2547;{{ number_format($totalIncome - $totalExpense, 2) }}</span>
            </div>
        </div>
    </div>

</div>

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">Filters</h3>
    </div>

    <div class="card-body">

        <form method="GET" action="{{ route('accounts.transactions.index') }}">

            <div class="row">

                <div class="col-md-3">
                    <label>From</label>
                    <input type="date" name="start" class="form-control" value="{{ $start->toDateString() }}">
                </div>

                <div class="col-md-3">
                    <label>To</label>
                    <input type="date" name="end" class="form-control" value="{{ $end->toDateString() }}">
                </div>

                <div class="col-md-2">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="">All</option>
                        <option value="income" {{ request('type') == 'income' ? 'selected' : '' }}>Income</option>
                        <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>Expense</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">All</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }} ({{ ucfirst($category->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>

            </div>

        </form>

    </div>

</div>

<div class="card card-outline card-secondary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Transactions</h3>
        <a href="{{ route('accounts.transactions.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Transaction
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Recorded By</th>
                    <th>Amount</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($transactions as $transaction)

                    <tr>
                        <td>{{ $transaction->transaction_date->format('d M Y') }}</td>
                        <td>
                            @if($transaction->category->type == 'income')
                                <span class="badge badge-success">INCOME</span>
                            @else
                                <span class="badge badge-danger">EXPENSE</span>
                            @endif
                        </td>
                        <td>{{ $transaction->category->name }}</td>
                        <td>{{ $transaction->description ?? '-' }}</td>
                        <td>{{ $transaction->recordedBy->name ?? '-' }}</td>
                        <td>&#2547;{{ number_format($transaction->amount, 2) }}</td>
                        <td>
                            @if($transaction->category->type == 'income')
                                <a href="{{ route('accounts.transactions.print', $transaction->id) }}" target="_blank" class="btn btn-info btn-sm">
                                    <i class="fas fa-print"></i>
                                </a>
                            @endif

                            <a href="{{ route('accounts.transactions.edit', $transaction->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            @if(strtolower(auth()->user()->role) == 'admin')
                                <form action="{{ route('accounts.transactions.destroy', $transaction->id) }}"
                                      method="POST"
                                      class="d-inline js-confirm-delete"
                                      data-confirm-message="Delete this transaction?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="7" class="text-center">No transactions in this period</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="card card-outline card-info">

    <div class="card-header">
        <h3 class="card-title">Categories</h3>
    </div>

    <div class="card-body">

        <div class="mb-3">
            @foreach($categories as $category)
                <span class="badge {{ $category->type == 'income' ? 'badge-success' : 'badge-danger' }} p-2 mr-1 mb-1">
                    {{ $category->name }}

                    <form action="{{ route('accounts.transaction-categories.destroy', $category->id) }}"
                          method="POST"
                          class="d-inline ml-1 js-confirm-delete"
                          data-confirm-message="Remove category {{ $category->name }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-link text-white p-0" style="text-decoration:none;">&times;</button>
                    </form>
                </span>
            @endforeach
        </div>

        <form method="POST" action="{{ route('accounts.transaction-categories.store') }}" class="form-inline">
            @csrf

            <input type="text" name="name" class="form-control mr-2 mb-2" placeholder="New category name" required>

            <select name="type" class="form-control mr-2 mb-2" required>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
            </select>

            <button type="submit" class="btn btn-info mb-2">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </form>

    </div>

</div>

@stop
