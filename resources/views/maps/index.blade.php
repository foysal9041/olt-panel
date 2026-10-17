@extends('adminlte::page')

@section('title', 'Weathermaps')

@section('content_header')
<x-noc.header title="Weathermaps" icon="fas fa-project-diagram"
    subtitle="Network maps whose links turn colour with their live traffic" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="row">
    @unless ($user->isViewer())
        <div class="col-lg-4 order-lg-2">
            <div class="card card-outline card-success">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle mr-1 text-success"></i> New weathermap</h3></div>
                <form method="POST" action="{{ route('maps.store') }}" class="card-body">
                    @csrf
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" maxlength="100" class="form-control" value="{{ old('name') }}" placeholder="e.g. Core &amp; upstream" required>
                    </div>
                    <div class="form-group">
                        <label>Description <small class="text-muted">(optional)</small></label>
                        <input type="text" name="description" maxlength="255" class="form-control" value="{{ old('description') }}" placeholder="e.g. IIG → core → POP switches">
                    </div>
                    <button class="btn btn-success btn-block"><i class="fas fa-plus"></i> Make weathermap</button>
                    <small class="d-block text-muted mt-2">Add nodes (switches, routers, or labels like "IIG"), drag them into place, then draw links and pick each link's interface.</small>
                </form>
            </div>
        </div>
    @endunless

    <div class="{{ $user->isViewer() ? 'col-12' : 'col-lg-8 order-lg-1' }}">
        @forelse ($maps as $m)
            <a href="{{ route('maps.show', $m) }}" class="card mb-3 text-reset d-block" style="text-decoration:none">
                <div class="card-body d-flex align-items-center flex-wrap" style="gap:.5rem 1rem">
                    <span class="d-inline-flex align-items-center justify-content-center rounded" style="width:2.6rem;height:2.6rem;background:#e0e7ff;color:#4338ca">
                        <i class="fas fa-project-diagram"></i>
                    </span>
                    <div class="mr-auto" style="min-width:12rem">
                        <div class="font-weight-bold" style="font-size:1.05rem">{{ $m->name }}</div>
                        @if ($m->description)<div class="small text-muted">{{ $m->description }}</div>@endif
                    </div>
                    <div class="small text-muted text-right">
                        <div><i class="fas fa-circle"></i> {{ $m->nodes_count }} nodes · <i class="fas fa-arrows-alt-h"></i> {{ $m->links_count }} links</div>
                        <div><i class="fas {{ $m->visibility === 'private' ? 'fa-lock' : 'fa-users' }}"></i> {{ $m->sharingLabel() }}</div>
                        <div><i class="fas fa-user"></i> {{ $m->user_id === $user->id ? 'You' : ($m->owner->name ?? '—') }}</div>
                    </div>
                </div>
            </a>
        @empty
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="fas fa-project-diagram fa-2x d-block mb-2"></i>
                    No weathermaps yet{{ $user->isViewer() ? ' shared with you' : '' }}.
                </div>
            </div>
        @endforelse
    </div>
</div>

@stop
