@extends('adminlte::page')

@section('title', 'Latency Targets')

@section('content_header')
<x-noc.header title="Latency Targets" icon="fas fa-bullseye" subtitle="Destinations pinged every minute, with alert thresholds" />
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/latency.css') }}?v={{ filemtime(public_path('css/latency.css')) }}">
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($targets->contains(fn ($t) => $t->hasThresholds() && $t->notify))
    @include('partials.telegram-off-banner')
@endif

<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Destinations</h3>
        <a href="{{ route('latency.targets.create') }}" class="btn btn-primary btn-sm ml-auto">
            <i class="fas fa-plus"></i> Add Target
        </a>
    </div>

    <div class="card-body p-0 table-responsive">

        <table class="table table-bordered table-striped data-table mb-0">

            <thead>
                <tr>
                    <th>Name</th>
                    <th>Host</th>
                    <th>Group</th>
                    <th>Pings</th>
                    <th>Threshold</th>
                    <th>Median</th>
                    <th>Loss</th>
                    <th>Status</th>
                    <th width="190">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($targets as $target)

                    <tr>
                        <td>{{ $target->name }}</td>
                        <td><code>{{ $target->host }}</code></td>
                        <td>{{ $target->group ?: '—' }}</td>
                        <td>{{ $target->pings }}</td>
                        <td class="text-nowrap">
                            @if ($target->hasThresholds())
                                @if ($target->latency_threshold !== null) &gt; {{ $target->latency_threshold }} ms @endif
                                @if ($target->latency_threshold !== null && $target->loss_threshold !== null) <br> @endif
                                @if ($target->loss_threshold !== null) ≥ {{ $target->loss_threshold }}% loss @endif
                                @unless ($target->notify) <i class="fas fa-bell-slash text-muted" title="Telegram alerts off"></i> @endunless
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-order="{{ $target->last_median ?? -1 }}">
                            {{ $target->last_median !== null ? round($target->last_median, 2).' ms' : '—' }}
                        </td>
                        <td data-order="{{ $target->last_loss ?? -1 }}">
                            {{ $target->last_loss !== null ? round($target->last_loss, 1).'%' : '—' }}
                        </td>
                        <td>
                            @if ($target->is_active)
                                @include('latency.partials.status', ['status' => $target->status])
                            @else
                                <span class="badge badge-secondary">Paused</span>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @can('access-latency-graphs')
                                <a href="{{ route('latency.show', $target) }}" class="btn btn-info btn-sm" title="Graphs">
                                    <i class="fas fa-chart-area"></i>
                                </a>
                            @endcan

                            <a href="{{ route('latency.targets.edit', $target) }}" class="btn btn-warning btn-sm">
                                Edit
                            </a>

                            <form action="{{ route('latency.targets.destroy', $target) }}"
                                  method="POST"
                                  class="d-inline js-confirm-delete"
                                  data-confirm-message="Delete {{ $target->name }} and all its latency history?">
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
                        <td colspan="9" class="text-center">No targets yet</td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@stop
