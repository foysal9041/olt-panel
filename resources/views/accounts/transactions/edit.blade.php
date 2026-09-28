@extends('adminlte::page')

@section('title', 'Edit Transaction')

@section('content_header')
<h1>Edit Transaction</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">Transaction Details</h3>
    </div>

    <form method="POST" action="{{ route('accounts.transactions.update', $transaction->id) }}">
        @csrf
        @method('PUT')

        @include('accounts.transactions.partials.form')

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('accounts.transactions.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
