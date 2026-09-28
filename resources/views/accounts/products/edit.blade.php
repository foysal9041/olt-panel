@extends('adminlte::page')

@section('title', 'Edit Product')

@section('content_header')
<h1>Edit Product</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $product->name }}</h3>
    </div>

    <form method="POST" action="{{ route('accounts.products.update', $product->id) }}">
        @csrf
        @method('PUT')

        @include('accounts.products.partials.form')

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('accounts.products.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
