@extends('adminlte::page')

@section('title', 'Add Zone')

@section('content_header')
<h1>Add Zone</h1>
@stop

@section('content')

<div class="card card-outline card-primary col-md-6">

    <div class="card-header">
        <h3 class="card-title">Zone Details</h3>
    </div>

    <form method="POST" action="{{ route('zones.store') }}">
        @csrf

        <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group">
                <label>Zone Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                       placeholder="e.g. Jashore, Head Office" required autofocus>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('zones.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
