@extends('adminlte::page')

@section('title', 'Add Customer')

@section('content_header')
<x-accounts.header title="Add Customer" subtitle="New MAC or bandwidth client" :back="route('accounts.customers.index')" />
@stop

@section('content')

<div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-plus mr-1 text-primary"></i> Customer details</h3>
            </div>
            <form method="POST" action="{{ route('accounts.customers.store') }}">
                @csrf
                @include('accounts.customers.partials.form')
                <div class="card-footer d-flex justify-content-end" style="gap:.5rem">
                    <a href="{{ route('accounts.customers.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    $('.select2-zone').select2({ theme: 'bootstrap4', width: '100%' });
});
</script>

@stop
