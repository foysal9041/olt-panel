@extends('adminlte::page')

@section('title', 'Products')

@section('content_header')
<x-accounts.header title="Products & Packages" icon="fas fa-box" subtitle="Packages and list prices used when billing" />
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
        <h3 class="card-title">Products</h3>
        <a href="{{ route('accounts.products.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Product
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Billing</th>
                    <th>Status</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($products as $product)

                    <tr>
                        <td>{{ $product->name }}</td>
                        <td><span class="badge badge-secondary">{{ $product->category->name }}</span></td>
                        <td>&#2547;{{ number_format($product->price, 2) }}</td>
                        <td>{{ $product->billing_cycle == 'monthly' ? 'Monthly' : 'One-time' }}</td>
                        <td>
                            @if($product->status)
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-danger">INACTIVE</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('accounts.products.edit', $product->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('accounts.products.destroy', $product->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete product {{ $product->name }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="6" class="text-center">No products yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

<div class="card card-outline card-info">

    <div class="card-header">
        <h3 class="card-title">Product Categories</h3>
    </div>

    <div class="card-body">

        <div class="mb-3">
            @foreach($categories as $category)
                <span class="badge badge-info p-2 mr-1 mb-1">
                    {{ $category->name }}

                    <form action="{{ route('accounts.product-categories.destroy', $category->id) }}"
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

        <form method="POST" action="{{ route('accounts.product-categories.store') }}" class="form-inline">
            @csrf

            <input type="text" name="name" class="form-control mr-2 mb-2" placeholder="e.g. IPTV, Wi-Fi Router" required>

            <button type="submit" class="btn btn-info mb-2">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </form>

    </div>

</div>

@stop
