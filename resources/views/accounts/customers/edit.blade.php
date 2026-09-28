@extends('adminlte::page')

@section('title', 'Edit Customer')

@section('content_header')
<h1>Edit Customer</h1>
@stop

@section('content')

<div class="row">

    <div class="col-md-6">

        <div class="card card-outline card-primary">

            <div class="card-header">
                <h3 class="card-title">{{ $customer->name }}</h3>
            </div>

            <form method="POST" action="{{ route('accounts.customers.update', $customer->id) }}">
                @csrf
                @method('PUT')

                @include('accounts.customers.partials.form')

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('accounts.customers.index') }}" class="btn btn-secondary">Cancel</a>
                </div>

            </form>

        </div>

    </div>

    <div class="col-md-6">

        @include('accounts.customers.partials.invoice-history')

    </div>

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
