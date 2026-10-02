@extends('adminlte::page')

@section('title', 'Edit Customer')

@section('content_header')
<x-accounts.header title="Edit Customer" subtitle="{{ $customer->name }}" :back="route('accounts.customers.show', $customer)" />
@stop

@section('content')

<div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit mr-1 text-primary"></i> {{ $customer->name }}</h3>
            </div>
            <form method="POST" action="{{ route('accounts.customers.update', $customer->id) }}">
                @csrf
                @method('PUT')
                @include('accounts.customers.partials.form')
                <div class="card-footer d-flex justify-content-end" style="gap:.5rem">
                    <a href="{{ route('accounts.customers.show', $customer) }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save changes</button>
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
