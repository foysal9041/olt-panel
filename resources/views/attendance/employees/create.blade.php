@extends('adminlte::page')

@section('title', 'Add Employee')

@section('content_header')
<x-attendance.header title="Add Employee" subtitle="Link a staff member to their device PIN" :back="route('attendance.employees.index')" />
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">Employee Details</h3>
    </div>

    <form method="POST" action="{{ route('attendance.employees.store') }}">
        @csrf

        @include('attendance.employees.partials.form')

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('attendance.employees.index') }}" class="btn btn-secondary">Cancel</a>
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
