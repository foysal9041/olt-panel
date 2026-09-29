@extends('adminlte::page')

@section('title', 'VLAN Management')

@section('content_header')
<x-noc.header title="VLAN Management" icon="fas fa-stream" subtitle="Free VLAN finder, duplicate checker and reserved VLANs" />
@stop

@php
    $sources = \App\Services\VlanInventory::SOURCES;
    $fmt = fn ($a, $b) => \App\Support\VlanRange::format($a, $b);
    $findUrl = fn (array $over) => route('vlans.index', array_filter(array_merge($find, $over), fn ($v) => $v !== null)) . '#finder';
    $netLabel = \App\Services\VlanInventory::networkLabel($net);
@endphp

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

{{-- VLAN space --}}
<form method="GET" action="{{ route('vlans.index') }}" class="form-inline mb-3">
    <label class="mr-2 font-weight-bold"><i class="fas fa-network-wired mr-1 text-primary"></i> Network</label>
    <select name="net" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
        <option value="">Core network</option>
        @foreach($ownZones as $z)
            <option value="{{ $z }}" @selected($net === $z)>{{ $z }} (own VLANs)</option>
        @endforeach
    </select>
    <span class="small text-muted">
        VLANs must be unique within a network. POPs with their own switch
        (<a href="{{ route('zones.index') }}">Zones → Own VLANs</a>) have their own VLAN space.
    </span>
</form>

{{-- Overview --}}
<div class="row">
    <div class="col-6 col-md-3">
        <div class="small-box bg-primary">
            <div class="inner"><h3>{{ number_format($usedCount) }}</h3><p>VLAN IDs in use · {{ $netLabel }}</p></div>
            <div class="icon"><i class="fas fa-stream"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ number_format(4094 - $usedCount) }}</h3><p>Free VLAN IDs · {{ $netLabel }}</p></div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $entries->count() }}</h3><p>Records (OLT · Pool · NTTN · VLAN)</p></div>
            <div class="icon"><i class="fas fa-list"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="#duplicates" class="small-box d-block {{ $duplicates ? 'bg-danger' : 'bg-secondary' }}">
            <div class="inner"><h3>{{ count($duplicates) }}</h3><p>Duplicate VLANs</p></div>
            <div class="icon"><i class="fas {{ $duplicates ? 'fa-exclamation-triangle' : 'fa-shield-alt' }}"></i></div>
        </a>
    </div>
</div>

<div class="row">

    {{-- Find free VLANs --}}
    <div class="col-lg-7">
        <div class="card card-outline card-success" id="finder">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-search-plus mr-1"></i> Find Free VLANs <span class="badge badge-light border ml-1">{{ $netLabel }}</span></h3>
                <div class="card-tools small text-muted">Consecutive VLANs not used anywhere</div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('vlans.index') }}#finder" class="form-row align-items-end">
                    @if($net)<input type="hidden" name="net" value="{{ $net }}">@endif
                    <div class="col-6 col-md-2 form-group">
                        <label class="small mb-0">How many</label>
                        <input type="number" name="count" min="1" max="500" value="{{ $find['count'] }}" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2 form-group">
                        <label class="small mb-0">From</label>
                        <input type="number" name="from" min="1" max="4094" value="{{ $find['from'] }}" class="form-control" id="find-from">
                    </div>
                    <div class="col-6 col-md-2 form-group">
                        <label class="small mb-0">To</label>
                        <input type="number" name="to" min="1" max="4094" value="{{ $find['to'] }}" class="form-control" id="find-to">
                    </div>
                    <div class="col-6 col-md-3 form-group">
                        <label class="small mb-0">Start on</label>
                        <select name="align" class="form-control">
                            <option value="1" @selected($find['align'] === 1)>Any number</option>
                            <option value="5" @selected($find['align'] === 5)>Multiple of 5</option>
                            <option value="10" @selected($find['align'] === 10)>Multiple of 10</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 form-group">
                        <button class="btn btn-success btn-block"><i class="fas fa-search"></i> Find</button>
                    </div>
                </form>

                <div class="mb-2">
                    <span class="small text-muted mr-1">Series:</span>
                    @foreach($series as $s)
                        <a href="{{ $findUrl(['from' => $s['from'], 'to' => $s['to']]) }}"
                           class="btn btn-xs mb-1 {{ $result && $find['from'] == $s['from'] && $find['to'] == $s['to'] ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $s['from'] }}–{{ $s['to'] }}
                            <span class="opacity-75">(used {{ $s['first'] }}…{{ $s['last'] }})</span>
                        </a>
                    @endforeach
                    <a href="{{ $findUrl(['from' => 2, 'to' => 4094]) }}"
                       class="btn btn-xs mb-1 {{ $result && $find['from'] == 2 && $find['to'] == 4094 ? 'btn-primary' : 'btn-outline-primary' }}">All (2–4094)</a>
                </div>

                @if($result)
                    <hr>

                    @if($result['next'])
                        @php [$a, $b] = $result['next']; @endphp
                        <div class="vlan-pick vlan-pick-main mb-3">
                            <div class="d-flex flex-wrap align-items-center">
                                <div class="mr-auto">
                                    <div class="small text-success font-weight-bold text-uppercase">
                                        <i class="fas fa-arrow-right"></i> Next in sequence
                                    </div>
                                    <div class="vlan-range">{{ $fmt($a, $b) }}</div>
                                    <div class="small text-muted">
                                        @if($result['last_used'])
                                            Continues after the last used VLAN in {{ $find['from'] }}–{{ $find['to'] }}: <strong>{{ $result['last_used'] }}</strong>
                                        @else
                                            Nothing used in {{ $find['from'] }}–{{ $find['to'] }} yet
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2 mt-md-0">
                                    <button type="button" class="btn btn-outline-secondary btn-sm js-copy" data-copy="{{ $fmt($a, $b) }}"><i class="far fa-copy"></i> Copy</button>
                                    <button type="button" class="btn btn-success btn-sm viewer-hide" data-toggle="collapse" data-target="#reserve-next"><i class="fas fa-bookmark"></i> Reserve</button>
                                </div>
                            </div>
                            @if($b - $a < 40)
                                <div class="vlan-ids mt-2">
                                    @for($v = $a; $v <= $b; $v++)<span class="badge badge-success">{{ $v }}</span>@endfor
                                </div>
                            @endif
                            @include('vlans.partials.reserve-form', ['id' => 'reserve-next', 'range' => $fmt($a, $b)])
                        </div>
                    @endif

                    @if($result['gaps'])
                        <div class="small text-muted font-weight-bold text-uppercase mb-1">
                            <i class="fas fa-puzzle-piece"></i> Or fill a free gap in the series
                        </div>
                        @foreach($result['gaps'] as $i => [$a, $b, $holeFrom, $holeTo])
                            <div class="vlan-pick mb-2 py-2">
                                <div class="d-flex flex-wrap align-items-center">
                                    <div class="mr-auto">
                                        <span class="font-weight-bold" style="font-size:1.15rem">{{ $fmt($a, $b) }}</span>
                                        <span class="small text-muted ml-2">free gap {{ $fmt($holeFrom, $holeTo) }} ({{ $holeTo - $holeFrom + 1 }} VLANs)</span>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary btn-xs js-copy" data-copy="{{ $fmt($a, $b) }}"><i class="far fa-copy"></i> Copy</button>
                                        <button type="button" class="btn btn-outline-success btn-xs viewer-hide" data-toggle="collapse" data-target="#reserve-gap-{{ $i }}"><i class="fas fa-bookmark"></i> Reserve</button>
                                    </div>
                                </div>
                                @include('vlans.partials.reserve-form', ['id' => 'reserve-gap-' . $i, 'range' => $fmt($a, $b)])
                            </div>
                        @endforeach
                    @endif

                    @if(! $result['next'] && ! $result['gaps'])
                        <div class="alert alert-warning mb-0">
                            No {{ $find['count'] }} consecutive free VLANs in {{ $find['from'] }}–{{ $find['to'] }}. Try a wider range.
                        </div>
                    @endif
                @else
                    <p class="text-muted small mb-0">
                        Enter how many VLANs you need and pick a series — you'll get the next VLANs in sequence
                        (after the last one used) and any free gaps, checked against OLTs, IP pools, NTTN links and VLAN Management.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">

        {{-- Check a VLAN --}}
        <div class="card card-outline card-info" id="check">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-question-circle mr-1"></i> Check a VLAN <span class="badge badge-light border ml-1">{{ $netLabel }}</span></h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('vlans.index') }}#check" class="input-group mb-2">
                    @if($net)<input type="hidden" name="net" value="{{ $net }}">@endif
                    <input type="text" name="check" value="{{ $check }}" class="form-control" placeholder="e.g. 2211 or 2505-2508" required>
                    <div class="input-group-append"><button class="btn btn-info"><i class="fas fa-search"></i> Check</button></div>
                </form>

                @if($checkResult !== null)
                    @if(! $checkResult)
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> VLAN <strong>{{ $check }}</strong> is free in {{ $netLabel }} — not used anywhere there.
                        </div>
                    @else
                        <div class="alert alert-danger py-2 mb-2">
                            <i class="fas fa-times-circle"></i> VLAN <strong>{{ $check }}</strong> is already in use:
                        </div>
                        <table class="table table-sm mb-0">
                            @foreach($checkResult as $e)
                                <tr>
                                    <td class="text-nowrap font-weight-bold">{{ $fmt($e['from'], $e['to']) }}</td>
                                    <td><span class="badge vlan-src vlan-src-{{ $e['source'] }}">{{ $sources[$e['source']] }}</span></td>
                                    <td>
                                        {{ $e['label'] }}
                                        @if($e['reserved'])<span class="badge badge-light">reserved</span>@endif
                                        @if($e['detail'])<div class="small text-muted">{{ $e['detail'] }}</div>@endif
                                    </td>
                                    <td class="text-right"><a href="{{ $e['url'] }}" title="Open"><i class="fas fa-external-link-alt"></i></a></td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                @elseif($check !== '')
                    <div class="alert alert-warning mb-0">Enter a VLAN number or range like 2505-2508.</div>
                @endif
            </div>
        </div>

        {{-- Duplicates --}}
        <div class="card card-outline {{ $duplicates ? 'card-danger' : 'card-secondary' }}" id="duplicates">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clone mr-1"></i> Duplicate Checker</h3>
                <div class="card-tools">
                    <span class="badge {{ $duplicates ? 'badge-danger' : 'badge-success' }}">{{ count($duplicates) }} found</span>
                </div>
            </div>
            <div class="card-body {{ $duplicates ? 'p-0' : '' }}">
                @if(! $duplicates)
                    <div class="text-success">
                        <i class="fas fa-shield-alt"></i>
                        No duplicate VLANs — in the core network and every POP, each VLAN is recorded only once.
                    </div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th>VLAN</th><th>Used by</th></tr></thead>
                        <tbody>
                        @foreach($duplicates as $d)
                            <tr>
                                <td class="font-weight-bold text-danger text-nowrap">
                                    {{ $fmt($d['from'], $d['to']) }}
                                    @if($d['network'])<div class="small text-muted font-weight-normal">{{ $d['network'] }}</div>@endif
                                </td>
                                <td>
                                    @foreach($d['entries'] as $e)
                                        <div>
                                            <span class="badge vlan-src vlan-src-{{ $e['source'] }}">{{ $sources[$e['source']] }}</span>
                                            {{ $e['label'] }}
                                            @if($e['reserved'])<span class="badge badge-light">reserved</span>@endif
                                            <span class="small text-muted">(VLAN {{ $fmt($e['from'], $e['to']) }})</span>
                                            <a href="{{ $e['url'] }}" class="ml-1" title="Open"><i class="fas fa-external-link-alt small"></i></a>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer small text-muted">
                Checks OLTs, IP pools, NTTN peering VLANs and VLAN Management, network by network. Saving any of them refuses a VLAN that's already used
                — except a <em>reserved</em> VLAN, which an OLT, pool or NTTN link may take.
            </div>
        </div>

    </div>
</div>

{{-- VLAN Management entries --}}
<div class="card card-outline card-primary">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Reserved &amp; Named VLANs</h3>
        <a href="{{ route('vlans.create') }}" class="btn btn-primary btn-sm ml-auto">
            <i class="fas fa-plus"></i> Add VLAN
        </a>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-bordered table-striped mb-0">

            <thead>
                <tr>
                    <th width="50">SL</th>
                    <th>VLAN</th>
                    <th>Name / Purpose</th>
                    <th>Zone</th>
                    <th>Status</th>
                    <th>In use by</th>
                    <th>Remarks</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse($vlans as $vlan)

                    <tr>
                        <td>{{ $loop->iteration }}</td>
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
                        <td>
                            @forelse($takenBy[$vlan->id] ?? [] as $e)
                                <div class="small">
                                    <span class="badge vlan-src vlan-src-{{ $e['source'] }}">{{ $sources[$e['source']] }}</span>
                                    {{ $e['label'] }} <span class="text-muted">({{ $fmt($e['from'], $e['to']) }})</span>
                                </div>
                            @empty
                                <span class="text-muted small">Not used yet</span>
                            @endforelse
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
                        <td colspan="8" class="text-center text-muted">No VLANs reserved yet — use Find Free VLANs → Reserve.</td>
                    </tr>

                @endforelse

            </tbody>

        </table>
        </div>
    </div>

</div>

{{-- The register: every VLAN recorded anywhere --}}
<div class="card card-outline card-primary" id="records">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list-ol mr-1"></i> All VLAN Records <span class="badge badge-light border ml-1">{{ $entries->count() }}</span></h3>
        <div class="card-tools small text-muted">OLTs, IP subnets, NTTN links and VLAN Management — one list</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-striped data-table mb-0">
            <thead>
                <tr><th class="pl-3">VLAN</th><th>Count</th><th>Source</th><th>Name</th><th>Zone</th><th>Network</th><th>Details</th><th data-orderable="false"></th></tr>
            </thead>
            <tbody>
            @foreach($entries as $e)
                <tr>
                    <td class="pl-3 font-weight-bold" data-order="{{ sprintf('%04d', $e['from']) }}">{{ $fmt($e['from'], $e['to']) }}</td>
                    <td>{{ $e['to'] - $e['from'] + 1 }}</td>
                    <td><span class="badge vlan-src vlan-src-{{ $e['source'] }}">{{ $sources[$e['source']] }}</span></td>
                    <td>{{ $e['label'] }}</td>
                    <td class="small">{{ $e['zone'] ?? '—' }}</td>
                    <td class="small">{{ $e['network'] ?? 'Core' }}</td>
                    <td class="small text-muted">{{ $e['detail'] ?: '—' }}</td>
                    <td class="text-right"><a href="{{ $e['url'] }}" title="Open"><i class="fas fa-external-link-alt"></i></a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.js-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var text = btn.dataset.copy, done = function () {
            var html = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(function () { btn.innerHTML = html; }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var t = document.createElement('textarea');
            t.value = text; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(t);
        }
    });
});
</script>

@stop
