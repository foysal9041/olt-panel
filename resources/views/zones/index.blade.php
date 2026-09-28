@extends('adminlte::page')

@section('title', 'Zones')

@section('content_header')
<x-noc.header title="Zones" icon="fas fa-map-marked-alt" subtitle="Service areas used across OLTs, staff and customers" />
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Zones</h3>
        <a href="{{ route('zones.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Zone
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Name</th>
                    <th>OLTs</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($zones as $zone)

                    <tr>
                        <td>{{ $zone->name }}</td>
                        <td>{{ $zone->olts_count }}</td>
                        <td>
                            <a href="{{ route('zones.edit', $zone->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('zones.destroy', $zone->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete zone {{ $zone->name }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="3" class="text-center">No zones yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
