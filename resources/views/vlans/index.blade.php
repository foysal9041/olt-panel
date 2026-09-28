@extends('adminlte::page')

@section('title', 'VLAN Management')

@section('content_header')
<x-noc.header title="VLAN Management" icon="fas fa-stream" subtitle="VLAN IDs and ranges allocated per zone" />
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
        <h3 class="card-title">VLANs</h3>
        <a href="{{ route('vlans.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add VLAN
        </a>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>VLAN</th>
                    <th>Name / Purpose</th>
                    <th>Zone</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($vlans as $vlan)

                    <tr>
                        <td class="font-weight-bold">{{ $vlan->vlan }}</td>
                        <td>{{ $vlan->name }}</td>
                        <td>{{ $vlan->zone ?? '—' }}</td>
                        <td>
                            @if($vlan->status == 'active')
                                <span class="badge badge-success">ACTIVE</span>
                            @else
                                <span class="badge badge-secondary">RESERVED</span>
                            @endif
                        </td>
                        <td>{{ $vlan->remarks ?? '—' }}</td>
                        <td>
                            <a href="{{ route('vlans.edit', $vlan->id) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('vlans.destroy', $vlan->id) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete VLAN {{ $vlan->vlan }}?">
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
                        <td colspan="6" class="text-center">No VLANs recorded yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
