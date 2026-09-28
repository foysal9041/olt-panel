@extends('adminlte::page')

@section('title', 'Add Customer')

@section('content_header')
<h1>Add Customer</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">Customer Details</h3>
    </div>

    <form method="POST" action="{{ route('accounts.customers.store') }}">
        @csrf

        @include('accounts.customers.partials.form')

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('accounts.customers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

<script>
$(function () {
    $('.select2-zone').select2({
        theme: 'bootstrap4',
        width: '100%',
    });
});
</script>

@stop
