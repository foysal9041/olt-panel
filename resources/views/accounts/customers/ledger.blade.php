@extends('adminlte::page')

@section('title', 'Customer Ledger')

@section('content_header')
<x-accounts.header title="Ledger — {{ $customer->name }}" subtitle="Invoices and payments, running balance" :back="route('accounts.customers.show', $customer)" />
@stop

@section('content')

<div class="card card-outline card-info">

    <div class="card-header d-flex justify-content-between align-items-center">

        <h3 class="card-title">
            <i class="fas fa-book mr-1"></i>
            {{ $customer->name }}
        </h3>

        <div>
            <a href="{{ route('accounts.customers.show', $customer->id) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i>
                Back to Customer
            </a>

            <a href="{{ route('accounts.customers.ledger.print', $customer->id) }}" target="_blank" class="btn btn-info btn-sm">
                <i class="fas fa-print"></i>
                Print
            </a>
        </div>

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped mb-0">

            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th class="text-right">Invoiced</th>
                    <th class="text-right">Received</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>

            <tbody>

                @forelse($entries as $entry)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('d M, Y') }}</td>
                        <td>{{ $entry['description'] }}</td>
                        <td class="text-right">{{ $entry['debit'] > 0 ? '৳' . number_format($entry['debit'], 2) : '—' }}</td>
                        <td class="text-right text-success">{{ $entry['credit'] > 0 ? '৳' . number_format($entry['credit'], 2) : '—' }}</td>
                        <td class="text-right">৳{{ number_format($entry['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No invoices on record for this customer yet.</td>
                    </tr>
                @endforelse

            </tbody>

            @if($entries->isNotEmpty())
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="2" class="text-right">Total</td>
                        <td class="text-right">৳{{ number_format($totalDebit, 2) }}</td>
                        <td class="text-right">৳{{ number_format($totalCredit, 2) }}</td>
                        <td class="text-right">৳{{ number_format($closingBalance, 2) }}</td>
                    </tr>
                </tfoot>
            @endif

        </table>

    </div>

    <div class="card-footer">
        @if($closingBalance > 0)
            <span class="badge badge-danger">Outstanding balance: &#2547;{{ number_format($closingBalance, 2) }}</span>
        @else
            <span class="badge badge-success">No outstanding balance</span>
        @endif
    </div>

</div>

@stop
