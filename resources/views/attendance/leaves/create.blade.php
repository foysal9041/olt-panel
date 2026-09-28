@extends('adminlte::page')

@section('title', 'Add Leave')

@section('content_header')
<h1>Add Leave</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">Leave Details</h3>
    </div>

    <form method="POST" action="{{ route('attendance.leaves.store') }}">
        @csrf

        @include('attendance.leaves.partials.form')

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('attendance.leaves.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
