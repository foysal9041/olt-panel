@extends('adminlte::page')

@section('title', 'Edit Employee')

@section('content_header')
<h1>Edit Employee</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $employee->name }}</h3>
    </div>

    <form method="POST" action="{{ route('attendance.employees.update', $employee->id) }}">
        @csrf
        @method('PUT')

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
