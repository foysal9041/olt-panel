@extends('adminlte::page')

@section('title', 'Port Events')

@section('content_header')
<x-noc.header title="Port Events" icon="fas fa-history" subtitle="Port up/down, switch reachability and Rx alarms" />
@stop

@section('content')

<div class="card card-outline card-primary">

    <div class="card-header">
        <form method="GET" class="form-inline">
            <select name="switch" class="form-control form-control-sm mr-2 mb-1">
                <option value="">All switches</option>
                @foreach ($switches as $id => $name)
                    <option value="{{ $id }}" @selected((string) request('switch') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <select name="type" class="form-control form-control-sm mr-2 mb-1">
                <option value="">All events</option>
                @foreach (\App\Models\SwitchEvent::TYPES as $key => [$label])
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary btn-sm mb-1"><i class="fas fa-filter"></i> Filter</button>
            @if (request('switch') || request('type'))
                <a href="{{ route('switch-events.index') }}" class="btn btn-link btn-sm mb-1">Clear</a>
            @endif
        </form>
    </div>

    <div class="card-body p-0">
        @include('switches._events_table', ['events' => $events, 'showSwitch' => true])
    </div>

    @if ($events->hasPages())
        <div class="card-footer">
            {{ $events->links() }}
        </div>
    @endif

</div>

@stop
