@extends('adminlte::page')

@section('title', 'NTTN Link Management')

@section('content_header')
<h1>NTTN Link Management</h1>
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
        <h3 class="card-title">NTTN Links</h3>
        <a href="{{ route('nttn-links.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add NTTN Link
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Link ID</th>
                    <th>Provider</th>
                    <th>Address</th>
                    <th>Bandwidth</th>
                    <th>Location</th>
                    <th>Zone</th>
                    <th>Status</th>
                    <th width="220">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($nttnLinks as $link)

                    <tr>
                        <td class="font-weight-bold">{{ $link->link_id }}</td>
                        <td>{{ $link->provider ?? '—' }}</td>
                        <td>{{ $link->address }}</td>
                        <td>{{ $link->bandwidth }}</td>
                        <td>{{ $link->location }}</td>
                        <td>{{ $link->zone ?? '—' }}</td>
                        <td>
                            @if($link->status == 'active')
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-danger">INACTIVE</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('nttn-links.show', $link->id) }}" class="btn btn-info btn-sm">
                                View
                            </a>

                            <a href="{{ route('nttn-links.edit', $link->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('nttn-links.destroy', $link->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete NTTN link {{ $link->link_id }}?">
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
                        <td colspan="8" class="text-center">No NTTN links recorded yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
