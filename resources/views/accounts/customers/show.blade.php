@extends('adminlte::page')

@section('title', $customer->name)

@use('App\Support\Dec')
@php
    $isBw = $customer->isBandwidthClient();
    $calcs = $rows->map(fn ($r) => $r->calc($r->settlement?->bkash_percent));
    $sum = fn (string $k) => $calcs->reduce(fn ($t, $c) => Dec::add($t, $c[$k]), '0');
@endphp

@section('content_header')
<x-accounts.header :title="$customer->name" :subtitle="$customer->customerTypeLabel() . ($customer->zone ? ' · ' . $customer->zone : '')" :back="route('accounts.customers.index')">
    <a href="{{ route('accounts.customers.edit', $customer) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen"></i> Edit</a>
</x-accounts.header>
@stop

@section('css')
<style>
    .cp-head { display: flex; align-items: center; gap: .9rem; padding: 1.2rem; }
    .cp-avatar { flex: none; display: grid; place-items: center; width: 3.4rem; height: 3.4rem; border-radius: 1rem; font-weight: 800; font-size: 1.1rem; text-transform: uppercase; }
    .cp-avatar.mac { background: #e0f2fe; color: #0369a1; }
    .cp-avatar.bw { background: #ede9fe; color: #6d28d9; }
    .cp-name { font-size: 1.15rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .cp-facts { margin: 0; padding: 0 1.2rem 1rem; list-style: none; }
    .cp-facts li { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-top: 1px solid #f1f5f9; font-size: .88rem; }
    .cp-facts li span { color: #64748b; }
    .cp-facts li b { font-weight: 600; color: #0f172a; text-align: right; }
    .cp-sec { padding: .7rem 1.2rem .3rem; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; border-top: 1px solid #eef2f7; }
    .cp-table th { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-top: 0; }
    .cp-table td, .cp-table th { vertical-align: middle; }
    .cp-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .cp-table tfoot td { font-weight: 700; background: #f8fafc; border-top: 2px solid #e2e8f0; }
</style>
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">

    {{-- ============ Profile ============ --}}
    <div class="col-lg-4">
        <div class="card acct-panel">
            <div class="cp-head">
                <span class="cp-avatar {{ $isBw ? 'bw' : 'mac' }}">{{ mb_substr($customer->name, 0, 2) }}</span>
                <div style="min-width:0">
                    <div class="cp-name">{{ $customer->name }}</div>
                    <div class="mt-1">
                        <span class="badge {{ $isBw ? 'badge-primary' : 'badge-info' }}">{{ $customer->customerTypeLabel() }}</span>
                        <span class="badge {{ $customer->status ? 'badge-success' : 'badge-secondary' }}">{{ $customer->status ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
            </div>
            <ul class="cp-facts">
                <li><span><i class="fas fa-at mr-1"></i> Username</span><b>{{ $customer->username ?: '—' }}</b></li>
                <li><span><i class="fas fa-phone mr-1"></i> Phone</span><b>@if ($customer->phone)<a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>@else — @endif</b></li>
                <li><span><i class="fas fa-map-marker-alt mr-1"></i> Zone</span><b>{{ $customer->zone ?: '—' }}</b></li>
                <li><span><i class="fas fa-home mr-1"></i> Address</span><b>{{ $customer->address ?: '—' }}</b></li>
            </ul>
            @if ($isBw)
                <div class="cp-sec">Key Account Manager</div>
                <ul class="cp-facts">
                    <li><span>Name</span><b>{{ $customer->kam_name ?: '—' }}</b></li>
                    <li><span>Phone</span><b>{{ $customer->kam_phone ?: '—' }}</b></li>
                </ul>
            @endif
            <div class="card-footer d-flex" style="gap:.5rem">
                <a href="{{ route('accounts.customers.edit', $customer) }}" class="btn btn-sm btn-primary flex-grow-1"><i class="fas fa-pen"></i> Edit customer</a>
                @if (strtolower(auth()->user()->role) === 'admin')
                    <form action="{{ route('accounts.customers.destroy', $customer) }}" method="POST" class="js-confirm-delete"
                          data-confirm-message="Delete customer {{ $customer->name }}?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">

        {{-- ============ Bandwidth rates ============ --}}
        @if ($isBw)
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-tachometer-alt mr-1" style="color:#7c3aed"></i> Bandwidth rates</h3>
                    <span class="font-weight-bold money">৳{{ number_format($customer->bandwidthRatesTotal(), 2) }} <small class="text-muted">/ month</small></span>
                </div>
                <div class="card-body p-0">
                    @if ($customer->bandwidthRates->isEmpty())
                        <div class="acct-empty"><i class="fas fa-tachometer-alt"></i>No rates set. <a href="{{ route('accounts.customers.edit', $customer) }}">Add rates</a></div>
                    @else
                        <table class="table table-sm cp-table mb-0">
                            <thead><tr><th class="pl-3">Type</th><th class="num">Rate (৳/Mbps)</th><th class="num">Mbps</th><th class="num pr-3">Monthly</th></tr></thead>
                            <tbody>
                                @foreach ($customer->bandwidthRates as $rate)
                                    <tr>
                                        <td class="pl-3">{{ $rate->bandwidthType?->name ?? '—' }}</td>
                                        <td class="num">{{ number_format($rate->rate, 2) }}</td>
                                        <td class="num">{{ rtrim(rtrim(number_format($rate->quantity, 2), '0'), '.') }}</td>
                                        <td class="num pr-3 font-weight-bold">৳{{ number_format($rate->lineTotal(), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        @if ($isBw)
            @php $bal = (float) $customer->bandwidthBalance(); @endphp
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-book mr-1 text-primary"></i> Account statement</h3>
                    <span class="d-flex align-items-center" style="gap:.6rem">
                        <span class="font-weight-bold {{ $bal > 0 ? 'text-danger' : 'text-success' }}">{{ $bal > 0 ? 'Due' : ($bal < 0 ? 'Advance' : 'Settled') }} ৳{{ number_format(abs($bal), 2) }}</span>
                        @can('access-accounts-billing')
                            <form method="POST" action="{{ route('accounts.billing.generate') }}" class="form-inline" style="gap:.3rem">
                                @csrf
                                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                                <input type="month" name="month" value="{{ now()->format('Y-m') }}" class="form-control form-control-sm" style="width:9.5rem" required>
                                <button class="btn btn-sm btn-primary text-nowrap"><i class="fas fa-file-invoice"></i> Make invoice</button>
                            </form>
                        @endcan
                    </span>
                </div>
                <div class="card-body p-0">
                    @if ($statement->isEmpty())
                        <div class="acct-empty"><i class="fas fa-file-invoice"></i>No bill yet — invoices are made on the 1st of every month.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm cp-table mb-0">
                                <thead><tr><th class="pl-3">Date</th><th>Entry</th><th class="num">Bill</th><th class="num">Paid</th><th class="num pr-3">Balance</th></tr></thead>
                                <tbody>
                                    @foreach ($statement as $e)
                                        <tr>
                                            <td class="pl-3 text-nowrap">{{ $e['date']?->format('d M Y') ?? '—' }}</td>
                                            <td>
                                                @if ($e['invoice'] && $e['bill'] !== null)
                                                    @can('access-accounts-billing')<a href="{{ route('accounts.billing.show', $e['invoice']) }}">{{ $e['text'] }}</a>@else {{ $e['text'] }} @endcan
                                                @else
                                                    {{ $e['text'] }}
                                                @endif
                                            </td>
                                            <td class="num">{{ $e['bill'] !== null ? number_format((float) $e['bill'], 2) : '' }}</td>
                                            <td class="num text-income">{{ $e['paid'] !== null ? number_format((float) $e['paid'], 2) : '' }}</td>
                                            <td class="num pr-3 font-weight-bold {{ (float) $e['balance'] > 0 ? 'text-danger' : '' }}">{{ number_format((float) $e['balance'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            @if ($monthly)
                @php
                    $used = collect($monthly)->flatMap(fn ($m) => array_keys($m['types']))->unique();
                    $cols = $typeOrder->filter(fn ($t) => $used->contains($t))->merge($used->diff($typeOrder))->values();
                    $n = fn ($v) => $v === null ? '' : (rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.') ?: '0');
                @endphp
                <div class="card acct-panel">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-alt mr-1 text-primary"></i> Month by month</h3>
                        <span class="small text-muted">Mbps × rate each month · Bill = what the rates give · Invoice = what was billed</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm cp-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="pl-3">Month</th>
                                        @foreach ($cols as $c)<th class="num">{{ $c }}</th>@endforeach
                                        <th class="num">Bill</th>
                                        <th class="num pr-3">Invoice</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monthly as $row)
                                        <tr>
                                            <td class="pl-3 font-weight-bold text-nowrap">{{ $row['month']->format('M Y') }}</td>
                                            @foreach ($cols as $c)
                                                <td class="num" style="line-height:1.25">
                                                    @foreach ($row['types'][$c] ?? [] as $seg)
                                                        <div @if (count($row['types'][$c]) > 1) title="{{ $seg['from']->format('j M') }} – {{ $seg['to']->format('j M') }}" @endif>
                                                            @if ($seg['mbps'] !== null)
                                                                <b>{{ $n($seg['mbps']) }}</b> <span class="text-muted">× {{ $n($seg['rate']) }}</span>
                                                            @else
                                                                <b>{{ $n($seg['rate']) }}</b>
                                                            @endif
                                                            @if (count($row['types'][$c]) > 1)<span class="text-muted small">({{ $seg['from']->format('j') }}–{{ $seg['to']->format('j') }})</span>@endif
                                                        </div>
                                                    @endforeach
                                                    @if (empty($row['types'][$c]))<span class="text-muted">—</span>@endif
                                                </td>
                                            @endforeach
                                            <td class="num">{{ number_format((float) $row['total'], 2) }}</td>
                                            <td class="num pr-3">
                                                @if ($row['invoice'])
                                                    @php $billed = $row['invoice']->totalBill(); @endphp
                                                    @can('access-accounts-billing')
                                                        <a href="{{ route('accounts.billing.show', $row['invoice']) }}" class="font-weight-bold">{{ number_format((float) $billed, 2) }}</a>
                                                    @else
                                                        <b>{{ number_format((float) $billed, 2) }}</b>
                                                    @endcan
                                                    @if (abs((float) $billed - (float) $row['total']) >= 0.01)
                                                        <i class="fas fa-info-circle text-muted" title="The invoice differs from the rates (edited lines, or billed another way then)"></i>
                                                    @endif
                                                @else
                                                    <span class="text-muted small">not made</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-1" style="color:#7c3aed"></i> Rate &amp; Mbps history</h3>
                    <a href="{{ route('accounts.customers.edit', $customer) }}" class="small">Change rates <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body p-0">
                    @if ($customer->serviceChanges->isEmpty())
                        <div class="acct-empty"><i class="fas fa-history"></i>No rates yet.</div>
                    @else
                        <table class="table table-sm cp-table mb-0">
                            <thead><tr><th class="pl-3">From</th><th>Type</th><th class="num">Rate</th><th class="num">Mbps</th><th class="num">Monthly</th><th class="pr-3">Note</th></tr></thead>
                            <tbody>
                                @foreach ($customer->serviceChanges->sortByDesc(fn ($c) => $c->effective_from->toDateString() . sprintf('%08d', $c->id)) as $c)
                                    <tr>
                                        <td class="pl-3 text-nowrap">{{ $c->effective_from->format('d M Y') }}</td>
                                        <td>{{ $c->type?->name }}</td>
                                        <td class="num">{{ (float) $c->rate > 0 ? rtrim(rtrim($c->rate, '0'), '.') : 'stopped' }}</td>
                                        <td class="num">{{ $c->mbps !== null && (float) $c->rate > 0 ? rtrim(rtrim($c->mbps, '0'), '.') : '' }}</td>
                                        <td class="num">{{ (float) $c->rate > 0 ? number_format((float) $c->rate * (float) ($c->mbps ?: 1), 2) : '' }}</td>
                                        <td class="pr-3 small text-muted">{{ $c->note }}{{ $c->recorder ? ($c->note ? ' · ' : '') . $c->recorder->name : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        {{-- ============ Zone settlement history ============ --}}
        @if (! $isBw || $rows->isNotEmpty())
            @if ($rows->isNotEmpty())
                <div class="acct-stats">
                    <div class="acct-stat" style="--accent:#0284c7">
                        <div class="acct-stat-label">Total Payment <i class="fas fa-coins"></i></div>
                        <div class="acct-stat-value">{{ Dec::taka($sum('payment')) }}</div>
                        <div class="acct-stat-foot">{{ $rows->count() }} {{ Str::plural('month', $rows->count()) }}</div>
                    </div>
                    <div class="acct-stat" style="--accent:#4f46e5">
                        <div class="acct-stat-label">Total Payable <i class="fas fa-file-invoice"></i></div>
                        <div class="acct-stat-value">{{ Dec::taka($sum('invoice')) }}</div>
                        <div class="acct-stat-foot">On their invoices</div>
                    </div>
                    <div class="acct-stat" style="--accent:#16a34a">
                        <div class="acct-stat-label">Net Bill <i class="fas fa-hand-holding-usd"></i></div>
                        <div class="acct-stat-value text-income">{{ Dec::taka($sum('income')) }}</div>
                        <div class="acct-stat-foot">Company income</div>
                    </div>
                </div>
            @endif

            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-excel mr-1 text-success"></i> Zone Settlement history</h3>
                    @can('access-accounts-settlements')
                        <a href="{{ route('accounts.settlements.index') }}" class="small">All settlements <i class="fas fa-arrow-right"></i></a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @if ($rows->isEmpty())
                        <div class="acct-empty"><i class="fas fa-file-excel"></i>Not in any settlement yet — rows are matched by username ({{ $customer->username ?: 'none set' }}).</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm cp-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="pl-3">Month</th>
                                        <th>Invoice</th>
                                        <th class="num">Total Payment</th>
                                        <th class="num">Total Payable</th>
                                        <th class="num">Net Bill</th>
                                        <th class="text-center pr-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $i => $row)
                                        @php $c = $calcs[$i]; $s = $row->settlement; @endphp
                                        <tr>
                                            <td class="pl-3 font-weight-bold">
                                                @can('access-accounts-settlements')
                                                    <a href="{{ route('accounts.settlements.show', $s) }}" style="color:#0f172a">{{ $s->month->format('M Y') }}</a>
                                                @else
                                                    {{ $s->month->format('M Y') }}
                                                @endcan
                                            </td>
                                            <td class="text-nowrap">
                                                @can('access-accounts-settlements')
                                                    <a href="{{ route('accounts.settlements.invoice', [$s, $row]) }}" target="_blank" title="Print invoice"><i class="fas fa-print small"></i> {{ $row->invoice_no }}</a>
                                                    <a href="{{ route('accounts.settlements.invoice', [$s, $row, 'download' => 1]) }}" target="_blank" class="ml-1 text-success" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                                                @else
                                                    {{ $row->invoice_no }}
                                                @endcan
                                            </td>
                                            <td class="num">{{ Dec::taka($c['payment']) }}</td>
                                            <td class="num">{{ Dec::taka($c['invoice']) }}</td>
                                            <td class="num font-weight-bold text-income">{{ Dec::taka($c['income']) }}</td>
                                            <td class="text-center pr-3">
                                                @if ($s->isClosed())
                                                    <i class="fas fa-lock text-success" title="Month closed in Net Profit"></i>
                                                @else
                                                    <i class="fas fa-pen text-muted" title="Month still open"></i>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                @if ($rows->count() > 1)
                                    <tfoot>
                                        <tr>
                                            <td class="pl-3" colspan="2">Total</td>
                                            <td class="num">{{ Dec::taka($sum('payment')) }}</td>
                                            <td class="num">{{ Dec::taka($sum('invoice')) }}</td>
                                            <td class="num text-income">{{ Dec::taka($sum('income')) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>
</div>

@stop
