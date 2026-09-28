@extends('adminlte::page')

@section('title', 'Edit Leave')

@section('content_header')
<x-attendance.header title="Edit Leave" :back="route('attendance.leaves.index')" />
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $leave->employee->name }}</h3>
    </div>

    <form method="POST" action="{{ route('attendance.leaves.update', $leave->id) }}">
        @csrf
        @method('PUT')

        @include('attendance.leaves.partials.form')

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('attendance.leaves.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
