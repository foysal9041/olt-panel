@extends('adminlte::page')

@section('title', 'Edit Latency Target')

@section('content_header')
<h1>Edit Latency Target</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-lg-8 px-0">

    <div class="card-header">
        <h3 class="card-title">Target Details</h3>
    </div>

    <form method="POST" action="{{ route('latency.targets.update', $target) }}">
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('latency.targets._form')
        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="{{ route('latency.targets.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
