@extends('adminlte::page')

@section('title', 'IP Management')

@section('content_header')
<x-noc.header title="IP Management" icon="fas fa-globe" subtitle="Every IP in one register — blocks, subnets, OLT & switch IPs and NTTN links">
    <a href="#records" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list-ol"></i> All IP Records</a>
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
    .badge-purple { background: #6f42c1; color: #fff; }
    .badge-orange { background: #fd7e14; color: #fff; }
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

@php
    $srcLabels = \App\Services\IpInventory::SOURCES;
    $roleLabels = \App\Services\IpInventory::ROLES;
    $srcColor = ['olt' => 'primary', 'switch' => 'info', 'pool' => 'purple', 'nttn' => 'orange', 'device' => 'secondary'];
@endphp

@php
    $fr = fn ($a, $b) => \App\Services\IpInventory::formatRange($a, $b);
    $ipOf = fn ($v) => long2ip($v);
    $blockFor = function ($min, $max) use ($blocks) {
        return $blocks->first(function ($b) use ($min, $max) {
            [$bMin, $bMax] = $b->range();
            return $min >= $bMin && $max <= $bMax;
        });
    };
    $allocUrl = function ($min, $max) use ($blockFor, $find) {
        $cidr = \App\Support\SubnetRange::rangeToCidrs($min, $max)[0];
        if (! str_contains($cidr, '/')) { $cidr .= '/32'; }
        return route('ip-pools.create', array_filter(['subnet' => $cidr, 'block' => $blockFor($min, $max)?->id]));
    };
    // "Gateway .85 · Device .86" for a /30, usable range otherwise.
    $usable = function ($min, $max) use ($ipOf) {
        $n = $max - $min + 1;
        if ($n === 1) return null;
        if ($n === 4) return 'Gateway ' . $ipOf($min + 1) . ' · Device ' . $ipOf($min + 2);
        if ($n === 2) return 'Usable ' . $ipOf($min) . ' – ' . $ipOf($max);
        return 'Usable ' . $ipOf($min + 1) . ' – ' . $ipOf($max - 1) . ' (' . ($n - 2) . ')';
    };
    $sizes = [32 => 'Single IP (/32)', 30 => '/30 — 4 IPs (P2P / device)', 29 => '/29 — 8 IPs', 28 => '/28 — 16 IPs', 27 => '/27 — 32 IPs', 26 => '/26 — 64 IPs', 25 => '/25 — 128 IPs', 24 => '/24 — 256 IPs'];
    $findUrl = fn (array $over) => route('ip-pools.index', array_merge(['size' => $find['size'], 'count' => $find['count']], $over)) . '#finder';
@endphp

<div class="row">

    {{-- Find free IPs --}}
    <div class="col-lg-7">
        <div class="card card-outline card-success" id="finder">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-search-plus mr-1"></i> Find Free IPs</h3>
                <div class="card-tools small text-muted">Not used by any OLT, switch, subnet or NTTN link</div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('ip-pools.index') }}#finder" class="form-row align-items-end">
                    <div class="col-md-5 form-group">
                        <label class="small mb-0">Range</label>
                        <input type="text" name="range" value="{{ $find['range'] }}" class="form-control mono" placeholder="192.168.50.0/24 or 192.168.50.1-100" required>
                    </div>
                    <div class="col-7 col-md-3 form-group">
                        <label class="small mb-0">Size</label>
                        <select name="size" class="form-control">
                            @foreach($sizes as $p => $label)
                                <option value="{{ $p }}" @selected($find['size'] === $p)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5 col-md-2 form-group">
                        <label class="small mb-0">How many</label>
                        <input type="number" name="count" min="1" max="64" value="{{ $find['count'] }}" class="form-control">
                    </div>
                    <div class="col-md-2 form-group">
                        <button class="btn btn-success btn-block"><i class="fas fa-search"></i> Find</button>
                    </div>
                </form>

                <div class="mb-2">
                    <span class="small text-muted mr-1">Ranges:</span>
                    @foreach($series as $sr)
                        <a href="{{ $findUrl(['range' => $sr['cidr']]) }}"
                           class="btn btn-xs mb-1 mono {{ $find['range'] === $sr['cidr'] ? 'btn-primary' : 'btn-outline-primary' }}"
                           title="{{ $sr['block'] ? 'IP block' : 'Has recorded IPs' }}">
                            {{ $sr['cidr'] }} <span class="opacity-75">· {{ $sr['used'] }} used</span>
                        </a>
                    @endforeach
                </div>

                @if($find['range'] !== '' && ! $findRange)
                    <div class="alert alert-warning mb-0">Enter a range like 192.168.50.0/24 or 192.168.50.1-100.</div>
                @elseif($found)
                    <hr>

                    @if($found['next'])
                        <div class="vlan-pick vlan-pick-main mb-3">
                            <div class="d-flex flex-wrap align-items-start">
                                <div class="mr-auto">
                                    <div class="small text-success font-weight-bold text-uppercase"><i class="fas fa-arrow-right"></i> Next in sequence</div>
                                    @foreach($found['next'] as [$a, $b])
                                        <div class="d-flex align-items-center flex-wrap">
                                            <span class="vlan-range mono mr-2" style="font-size:1.35rem">{{ $fr($a, $b) }}</span>
                                            @if($u = $usable($a, $b))<span class="small text-muted mr-2">{{ $u }}</span>@endif
                                            <a href="{{ $allocUrl($a, $b) }}" class="btn btn-success btn-xs viewer-hide"><i class="fas fa-plus"></i> Allocate</a>
                                        </div>
                                    @endforeach
                                    <div class="small text-muted mt-1">
                                        @if($found['last_used'])
                                            Continues after the last used IP in this range: <strong class="mono">{{ $ipOf($found['last_used']) }}</strong>
                                        @else
                                            Nothing recorded in this range yet
                                        @endif
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm js-copy mt-1" data-copy="{{ collect($found['next'])->map(fn ($r) => $fr($r[0], $r[1]))->implode(', ') }}"><i class="far fa-copy"></i> Copy</button>
                            </div>
                        </div>
                    @elseif($found['last_used'])
                        <div class="alert alert-light border small mb-3">
                            No room after the last used IP (<span class="mono">{{ $ipOf($found['last_used']) }}</span>) in this range — use a gap below or another range.
                        </div>
                    @endif

                    @if($found['gaps'])
                        <div class="small text-muted font-weight-bold text-uppercase mb-1"><i class="fas fa-puzzle-piece"></i> Or fill a free gap</div>
                        @foreach($found['gaps'] as $g)
                            <div class="vlan-pick mb-2 py-2">
                                <div class="d-flex flex-wrap align-items-start">
                                    <div class="mr-auto">
                                        @foreach($g['blocks'] as [$a, $b])
                                            <div class="d-flex align-items-center flex-wrap">
                                                <span class="font-weight-bold mono mr-2" style="font-size:1.05rem">{{ $fr($a, $b) }}</span>
                                                @if($u = $usable($a, $b))<span class="small text-muted mr-2">{{ $u }}</span>@endif
                                                <a href="{{ $allocUrl($a, $b) }}" class="btn btn-outline-success btn-xs viewer-hide"><i class="fas fa-plus"></i> Allocate</a>
                                            </div>
                                        @endforeach
                                        <div class="small text-muted">free space {{ $fr($g['free'][0], $g['free'][1]) }} ({{ number_format($g['free'][1] - $g['free'][0] + 1) }} IPs)</div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-xs js-copy mt-1" data-copy="{{ collect($g['blocks'])->map(fn ($r) => $fr($r[0], $r[1]))->implode(', ') }}"><i class="far fa-copy"></i> Copy</button>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    @if(! $found['next'] && ! $found['gaps'])
                        <div class="alert alert-warning mb-0">No free {{ $find['count'] }} × /{{ $find['size'] }} in {{ $find['range'] }}. Try a smaller size or another range.</div>
                    @endif
                @else
                    <p class="text-muted small mb-0">
                        Pick a range and size — e.g. <strong>/30</strong> for a new OLT or switch (gateway .1, device .2).
                        You'll get the next block in sequence and any free gaps; <em>Allocate</em> records it in IP Management.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Look up one IP / range --}}
    <div class="col-lg-5">
        <div class="card card-outline card-info" id="lookup">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-search mr-1"></i> Check an IP or Range</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('ip-pools.index') }}#lookup" class="input-group mb-2">
                    <input type="text" name="ip" value="{{ $lookupQuery }}" class="form-control mono" placeholder="192.168.50.58 · 103.161.2.196/30 · 192.168.50.1-100" required>
                    <div class="input-group-append"><button class="btn btn-info"><i class="fas fa-search"></i> Check</button></div>
                </form>

                @if($lookupQuery !== '')
                    @if(! $lookup)
                        <div class="alert alert-warning mb-0">Enter an IP, a subnet like 103.161.2.196/30, or a range like 192.168.50.1-100.</div>
                    @else
                        @if($lookup['block'])
                            <div class="small text-muted mb-2">Inside block <a href="{{ route('ip-blocks.show', $lookup['block']) }}" class="mono">{{ $lookup['block']->cidr }}</a> ({{ $lookup['block']->name }})</div>
                        @endif

                        @if($lookup['usage'])
                            @php $u = $lookup['usage']; @endphp
                            <div class="d-flex mb-2 text-center">
                                <div class="flex-fill border rounded py-1 mr-1"><div class="font-weight-bold">{{ number_format($u['size']) }}</div><div class="small text-muted">IPs in range</div></div>
                                <div class="flex-fill border rounded py-1 mr-1"><div class="font-weight-bold text-danger">{{ number_format($u['used']) }}</div><div class="small text-muted">used</div></div>
                                <div class="flex-fill border rounded py-1"><div class="font-weight-bold text-success">{{ number_format($u['free']) }}</div><div class="small text-muted">free</div></div>
                            </div>
                            @if($u['holes'])
                                <div class="small mb-2">
                                    <span class="text-muted">Free:</span>
                                    @foreach(array_slice($u['holes'], 0, 12) as [$a, $b])
                                        <span class="badge badge-success mono font-weight-normal">{{ $fr($a, $b) }}</span>
                                    @endforeach
                                    @if(count($u['holes']) > 12)<span class="text-muted">… {{ count($u['holes']) - 12 }} more</span>@endif
                                </div>
                            @endif
                        @endif

                        @if(! $lookup['entries'])
                            <div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> <span class="mono">{{ $lookupQuery }}</span> is not recorded anywhere — free to use.</div>
                        @else
                            <div style="max-height: 22rem; overflow-y: auto">
                            <table class="table table-sm mb-0">
                                @foreach($lookup['entries'] as $e)
                                    <tr>
                                        <td class="mono text-nowrap">{{ $e['text'] }}</td>
                                        <td><span class="badge badge-{{ $srcColor[$e['source']] }}">{{ $srcLabels[$e['source']] }}</span></td>
                                        <td>
                                            {{ $e['label'] }} <span class="small text-muted">· {{ $roleLabels[$e['role']] }}</span>
                                            @if($e['zone'])<div class="small text-muted">{{ $e['zone'] }}</div>@endif
                                        </td>
                                        <td class="text-right"><a href="{{ $e['url'] }}" title="Open"><i class="fas fa-external-link-alt"></i></a></td>
                                    </tr>
                                @endforeach
                            </table>
                            </div>
                        @endif
                    @endif
                @else
                    <p class="text-muted small mb-0">An IP, subnet or range: shows who uses it, and for a range how many IPs are used and which are free.</p>
                @endif
            </div>
        </div>
    </div>
</div>

        <div class="card card-outline {{ $duplicates ? 'card-danger' : 'card-secondary' }}" id="ip-duplicates">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clone mr-1"></i> Duplicate IP Checker</h3>
                <div class="card-tools"><span class="badge {{ $duplicates ? 'badge-danger' : 'badge-success' }}">{{ count($duplicates) }} found</span></div>
            </div>
            <div class="card-body {{ $duplicates ? 'p-0' : '' }}">
                @if(! $duplicates)
                    <div class="text-success"><i class="fas fa-shield-alt"></i> No IP is given out twice.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th class="pl-3">IP / Subnet</th><th>Used by</th></tr></thead>
                        <tbody>
                        @foreach($duplicates as $d)
                            <tr>
                                <td class="pl-3 mono font-weight-bold text-danger text-nowrap">{{ $d['text'] }}</td>
                                <td>
                                    @foreach($d['entries'] as $e)
                                        <div>
                                            <span class="badge badge-{{ $srcColor[$e['source']] }}">{{ $srcLabels[$e['source']] }}</span>
                                            {{ $e['label'] }}
                                            <span class="small text-muted">· {{ $roleLabels[$e['role']] }} <span class="mono">{{ $e['text'] }}</span></span>
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
                Same IP on two records, or two overlapping subnets. A host inside a subnet is fine; NTTN ping targets and
                attendance devices' connecting addresses are listed but not counted. Saving an OLT or switch refuses an IP that's already used.
            </div>
        </div>


{{-- The register --}}
<div class="card card-outline card-primary" id="records">
    <div class="card-header d-flex flex-wrap align-items-center">
        <h3 class="card-title mr-auto"><i class="fas fa-list-ol mr-1"></i> All IP Records <span class="badge badge-light border ml-1">{{ $records->count() }}</span></h3>
        <form method="GET" action="{{ route('ip-pools.index') }}#records" class="form-inline">
            <select name="src" class="form-control form-control-sm mr-2 mb-1" onchange="this.form.submit()">
                <option value="">All sources</option>
                @foreach($srcLabels as $key => $label)
                    <option value="{{ $key }}" @selected($filter['src'] === $key)>{{ $label }} ({{ $sourceCounts[$key] ?? 0 }})</option>
                @endforeach
            </select>
            <select name="zone" class="form-control form-control-sm mb-1" onchange="this.form.submit()">
                <option value="">All zones</option>
                <option value="_none" @selected($filter['zone'] === '_none')>— No zone —</option>
                @foreach($zones as $z)
                    <option value="{{ $z }}" @selected($filter['zone'] === $z)>{{ $z }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-striped table-hover data-table mb-0">
            <thead>
                <tr>
                    <th class="pl-3">IP / Subnet</th>
                    <th>Type</th>
                    <th>Source</th>
                    <th>Name</th>
                    <th>Zone</th>
                    <th>Details</th>
                    <th data-orderable="false"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($records as $e)
                <tr>
                    <td class="pl-3 mono font-weight-bold" data-order="{{ sprintf('%010d', $e['min']) }}">{{ $e['text'] }}</td>
                    <td class="small">{{ $roleLabels[$e['role']] }}</td>
                    <td><span class="badge badge-{{ $srcColor[$e['source']] }}">{{ $srcLabels[$e['source']] }}</span></td>
                    <td>{{ $e['label'] }}</td>
                    <td class="small">{{ $e['zone'] ?? '—' }}</td>
                    <td class="small text-muted">{{ $e['detail'] ?: '—' }}</td>
                    <td class="text-right pr-3"><a href="{{ $e['url'] }}" title="Open"><i class="fas fa-external-link-alt"></i></a></td>
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
