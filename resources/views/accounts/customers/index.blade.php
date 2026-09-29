@extends('adminlte::page')

@section('title', 'Customers')

@section('content_header')
<x-accounts.header title="Customers" icon="fas fa-users" subtitle="Mac and bandwidth clients, their rates and dues" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Customer List</h3>
        <a href="{{ route('accounts.customers.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add Customer
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Zone</th>
                    <th>Type</th>
                    <th>Package / Rate</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th width="300">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($customers as $customer)

                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->phone ?? '-' }}</td>
                        <td>{{ $customer->zone ?? '-' }}</td>
                        <td>
                            @if($customer->customer_type == 'corporate_client')
                                <span class="badge badge-primary">{{ $customer->customerTypeLabel() }}</span>
                            @elseif($customer->isBandwidthClient())
                                <span class="badge badge-info">{{ $customer->customerTypeLabel() }}</span>
                            @else
                                <span class="badge badge-secondary">{{ $customer->customerTypeLabel() }}</span>
                            @endif
                        </td>
                        <td>
                            @if($customer->usesPackage())
                                <div>
                                    @if($customer->product)
                                        {{ $customer->product->name }}
                                        (&#2547;{{ number_format($customer->package_rate ?? $customer->product->price, 2) }})
                                    @else
                                        <span class="text-muted">No package assigned</span>
                                    @endif
                                </div>
                            @endif
                            @if($customer->isBandwidthClient())
                                <div>
                                    @if($customer->bandwidthRates->isEmpty())
                                        <span class="text-muted">No rates set</span>
                                    @else
                                        &#2547;{{ number_format($customer->bandwidthRatesTotal(), 2) }}/mo
                                        <span class="text-muted">({{ $customer->bandwidthRates->count() }} type{{ $customer->bandwidthRates->count() == 1 ? '' : 's' }})</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @if(($customer->outstanding_due ?? 0) > 0)
                                <span class="badge badge-danger">&#2547;{{ number_format($customer->outstanding_due, 2) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($customer->status)
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-danger">INACTIVE</span>
                            @endif
                        </td>
                        <td>
                            @if(($customer->outstanding_due ?? 0) > 0)
                                @can('access-accounts-invoices')
                                    <a href="{{ route('accounts.payments.create', ['customer' => $customer->id]) }}" class="btn btn-success btn-sm">
                                        <i class="fas fa-hand-holding-usd"></i> Receive
                                    </a>
                                @endcan
                            @endif

                            <a href="{{ route('accounts.customers.show', $customer->id) }}" class="btn btn-info btn-sm">
                                View
                            </a>

                            <a href="{{ route('accounts.customers.show', $customer->id) }}#invoice-history" class="btn btn-secondary btn-sm">
                                <i class="fas fa-history"></i> History
                            </a>

                            <a href="{{ route('accounts.customers.ledger', $customer->id) }}" class="btn btn-info btn-sm">
                                <i class="fas fa-book"></i> Ledger
                            </a>

                            <a href="{{ route('accounts.customers.edit', $customer->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            @if(strtolower(auth()->user()->role) == 'admin')
                                <form action="{{ route('accounts.customers.destroy', $customer->id) }}"
                                      method="POST"
                                      class="d-inline js-confirm-delete"
                                      data-confirm-message="Delete customer {{ $customer->name }}?">
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
                        <td colspan="8" class="text-center">No customers yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="card card-outline card-info">

    <div class="card-header">
        <h3 class="card-title">Bandwidth Types</h3>
    </div>

    <div class="card-body">

        <div class="mb-3">
            @forelse($bandwidthTypes as $type)
                <span class="badge badge-info p-2 mr-1 mb-1">
                    {{ $type->name }}

                    <form action="{{ route('accounts.bandwidth-types.destroy', $type->id) }}"
                          method="POST"
                          class="d-inline ml-1 js-confirm-delete"
                          data-confirm-message="Remove bandwidth type {{ $type->name }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-link text-white p-0" style="text-decoration:none;">&times;</button>
                    </form>
                </span>
            @empty
                <span class="text-muted">No bandwidth types yet</span>
            @endforelse
        </div>

        <form method="POST" action="{{ route('accounts.bandwidth-types.store') }}" class="form-inline">
            @csrf

            <input type="text" name="name" class="form-control mr-2 mb-2" placeholder="New bandwidth type e.g. VAS" required>

            <button type="submit" class="btn btn-info mb-2">
                <i class="fas fa-plus"></i> Add Bandwidth Type
            </button>
        </form>

        <small class="text-muted d-block mt-2">
            These show up as rate fields on any customer set as a Bandwidth Client.
        </small>

    </div>

</div>

@stop
