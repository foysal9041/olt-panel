@extends('adminlte::page')

@section('title', 'Graph Dashboards')

@section('content_header')
<x-noc.header title="Graph Dashboards" icon="fas fa-chart-area"
    subtitle="Your own pages of interface traffic graphs — pick any interfaces, share with roles" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="row">
    @unless ($user->isViewer())
        <div class="col-lg-4 order-lg-2">
            <div class="card card-outline card-success">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle mr-1 text-success"></i> New dashboard</h3></div>
                <form method="POST" action="{{ route('graphs.store') }}" class="card-body">
                    @csrf
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" maxlength="100" class="form-control" value="{{ old('name') }}" placeholder="e.g. Core uplinks" required>
                    </div>
                    <div class="form-group">
                        <label>Description <small class="text-muted">(optional)</small></label>
                        <input type="text" name="description" maxlength="255" class="form-control" value="{{ old('description') }}" placeholder="e.g. IIG, GGC and NTTN uplinks">
                    </div>
                    <button class="btn btn-success btn-block"><i class="fas fa-plus"></i> Make dashboard</button>
                    <small class="d-block text-muted mt-2">It starts private — add graphs, then share it with roles from its settings.</small>
                </form>
            </div>
        </div>
    @endunless

    <div class="{{ $user->isViewer() ? 'col-12' : 'col-lg-8 order-lg-1' }}">
        @forelse ($dashboards as $d)
            <a href="{{ route('graphs.show', $d) }}" class="card mb-3 text-reset d-block" style="text-decoration:none">
                <div class="card-body d-flex align-items-center flex-wrap" style="gap:.5rem 1rem">
                    <span class="d-inline-flex align-items-center justify-content-center rounded" style="width:2.6rem;height:2.6rem;background:#dcfce7;color:#16a34a">
                        <i class="fas fa-chart-area"></i>
                    </span>
                    <div class="mr-auto" style="min-width:12rem">
                        <div class="font-weight-bold" style="font-size:1.05rem">{{ $d->name }}</div>
                        @if ($d->description)<div class="small text-muted">{{ $d->description }}</div>@endif
                    </div>
                    <div class="small text-muted text-right">
                        <div><i class="fas fa-th-large"></i> {{ $d->widgets_count }} {{ \Illuminate\Support\Str::plural('graph', $d->widgets_count) }}</div>
                        <div>
                            @if ($d->visibility === 'private')
                                <i class="fas fa-lock"></i>
                            @else
                                <i class="fas fa-users"></i>
                            @endif
                            {{ $d->sharingLabel() }}
                        </div>
                        <div><i class="fas fa-user"></i> {{ $d->user_id === $user->id ? 'You' : ($d->owner->name ?? '—') }}</div>
                    </div>
                </div>
            </a>
        @empty
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="fas fa-chart-area fa-2x d-block mb-2"></i>
                    No dashboards yet{{ $user->isViewer() ? ' shared with you' : '' }}.
                    @unless ($user->isViewer()) Make one on the right — e.g. "Core uplinks" with the IIG and GGC interfaces. @endunless
                </div>
            </div>
        @endforelse
    </div>
</div>

@stop
