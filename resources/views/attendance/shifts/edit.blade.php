@extends('adminlte::page')

@section('title', 'Edit Duty Shift')

@section('content_header')
<x-attendance.header title="Edit Duty Shift" :back="route('attendance.shifts.index')" />
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">{{ $shift->name }}</h3>
    </div>

    <form method="POST" action="{{ route('attendance.shifts.update', $shift->id) }}">
        @csrf
        @method('PUT')

        @include('attendance.shifts.partials.form')

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('attendance.shifts.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
