@extends('adminlte::page')

@section('title', 'IP Management')

@section('content_header')
<x-noc.header title="IP Management" icon="fas fa-globe" subtitle="Address blocks, the subnets carved out of them, and what's still free">
    <a href="{{ route('ip-pools.create') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-plus"></i> Subnet</a>
    <a href="{{ route('ip-blocks.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Block</a>
</x-noc.header>
@stop

@section('css')
<style>
    .ipb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
    .ipb-card { display: block; padding: 1.2rem 1.3rem; border-radius: .9rem; background: #fff; color: inherit;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .08); transition: transform .15s ease, box-shadow .15s ease; }
    .ipb-card:hover { color: inherit; text-decoration: none; transform: translateY(-2px); box-shadow: 0 12px 24px -10px rgba(15, 23, 42, .25); }
    .ipb-cidr { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
    .ipb-name { color: #64748b; font-size: .88rem; }
    .ipb-bar { height: .55rem; border-radius: 999px; background: #eef2f7; overflow: hidden; margin: .9rem 0 .5rem; }
    .ipb-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #6366f1, #8b5cf6); }
    .ipb-stats { display: flex; justify-content: space-between; font-size: .82rem; color: #64748b; }
    .ipb-stats b { color: #0f172a; }
    .ipb-empty { padding: 2.5rem 1rem; text-align: center; color: #64748b; background: #fff; border-radius: .9rem; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($blocks->isEmpty())
    <div class="ipb-empty mb-4">
        <i class="fas fa-cubes fa-2x mb-2 d-block" style="color:#cbd5e1"></i>
        <strong>No IP blocks yet.</strong><br>
        Add the range you own (e.g. <code>103.161.2.0/24</code>), then allocate subnets from its free space.
        <div class="mt-3"><a href="{{ route('ip-blocks.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Block</a></div>
    </div>
@else
    <div class="ipb-grid">
        @foreach ($blocks as $block)
            @php
                $used = $block->usedCount($block->allocations);
                $size = $block->size();
                $pct = $size ? round($used / $size * 100) : 0;
            @endphp
            <a href="{{ route('ip-blocks.show', $block) }}" class="ipb-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="ipb-cidr">{{ $block->cidr }}</div>
                        <div class="ipb-name">{{ $block->name }}</div>
                    </div>
                    <span class="badge {{ $block->type === 'public' ? 'badge-primary' : 'badge-secondary' }}">{{ strtoupper($block->type) }}</span>
                </div>
                <div class="ipb-bar"><span style="width: {{ $pct }}%"></span></div>
                <div class="ipb-stats">
                    <span><b>{{ $pct }}%</b> used</span>
                    <span><b>{{ $block->allocations->count() }}</b> subnets</span>
                    <span><b>{{ number_format($size - $used) }}</b> of {{ number_format($size) }} IPs free</span>
                </div>
            </a>
        @endforeach
    </div>
@endif

@if ($standalone->isNotEmpty())
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h3 class="card-title">Other Subnets <small class="text-muted">— not inside any block</small></h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">Subnet</th>
                        <th>Device</th>
                        <th>Purpose</th>
                        <th>Private IP</th>
                        <th>VLAN</th>
                        <th>Type</th>
                        <th class="text-right pr-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($standalone as $pool)
                        <tr>
                            <td class="pl-3 mono font-weight-bold">{{ $pool->subnet }}</td>
                            <td>{{ $pool->device ?: '—' }}</td>
                            <td>{{ $pool->purpose ?: '—' }}</td>
                            <td class="mono">{{ $pool->private_subnet ?: '—' }}</td>
                            <td class="mono small">{{ $pool->vlan ?: '—' }}</td>
                            <td><span class="badge badge-{{ $pool->type === 'public' ? 'primary' : 'secondary' }}">{{ strtoupper($pool->type) }}</span></td>
                            <td class="text-right pr-3 text-nowrap">
                                <a href="{{ route('ip-pools.edit', $pool) }}" class="btn btn-sm btn-light"><i class="fas fa-edit"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@stop
