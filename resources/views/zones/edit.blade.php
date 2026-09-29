@extends('adminlte::page')

@section('title', 'Edit Zone')

@section('content_header')
<x-noc.header title="Edit Zone" subtitle="{{ $zone->name }}" :back="route('zones.index')" />
@stop

@section('content')

<div class="card card-outline card-primary col-lg-8">

    <div class="card-header">
        <h3 class="card-title">{{ $zone->name }}</h3>
    </div>

    <form method="POST" action="{{ route('zones.update', $zone->id) }}">
        @csrf
        @method('PUT')

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

            @include('zones._fields', ['zone' => $zone ?? new \App\Models\Zone()])

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('zones.index') }}" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</div>

@stop
