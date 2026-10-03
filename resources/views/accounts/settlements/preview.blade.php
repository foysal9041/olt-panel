@extends('adminlte::page')

@section('title', 'Check settlement')

@use('App\Support\Dec')
@php
    $rowsOut = collect($result['rows']);
    $counted = $rowsOut->where('included', true);
    $errors_ = $rowsOut->filter(fn ($r) => collect($r['flags'])->contains(fn ($f) => $f[0] === 'error'));
    $warns = $rowsOut->filter(fn ($r) => $r['included'] && collect($r['flags'])->contains(fn ($f) => $f[0] === 'warn'));
    $skipped = $rowsOut->filter(fn ($r) => in_array($r['kind'], ['duplicate', 'total'], true));
    $colLabel = fn ($c) => $c . (isset($headers[$c]) && is_scalar($headers[$c]) && trim((string) $headers[$c]) !== '' ? ' — ' . \Illuminate\Support\Str::limit(trim((string) $headers[$c]), 28) : '');
    $firstNo = \App\Http\Controllers\Accounts\ZoneSettlementController::INVOICE_PREFIX . str_pad((string) $nextInvoice, 3, '0', STR_PAD_LEFT);
    $lastNo = \App\Http\Controllers\Accounts\ZoneSettlementController::INVOICE_PREFIX . str_pad((string) ($nextInvoice + max($counted->count() - 1, 0)), 3, '0', STR_PAD_LEFT);
    $flagIcon = ['error' => ['badge-danger', 'fas fa-times-circle'], 'warn' => ['badge-warning', 'fas fa-exclamation-triangle'], 'info' => ['badge-secondary', 'fas fa-info-circle']];
@endphp

@section('content_header')
<x-accounts.header title="Check before saving" icon="fas fa-file-excel" :back="route('accounts.settlements.index')"
    :subtitle="$upload['name'] . ' — nothing is saved until you press Save'" />
@stop

@section('css')
<style>
    .stl-map label { font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-bottom: .2rem; }
    .stl-table td, .stl-table th { white-space: nowrap; }
    .stl-table tr.out td { color: #94a3b8; background: #fafafa; }
    .stl-table tr.out td.flags { color: inherit; }
    .stl-table td.flags { white-space: normal; min-width: 16rem; }
    .stl-flag { display: block; font-size: .76rem; }
</style>
@stop

@section('content')

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

{{-- What was detected; change anything and it re-checks --}}
<div class="card acct-panel">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-columns mr-1 text-primary"></i> Sheet &amp; columns</h3>
        <span class="small text-muted">Found automatically — change if something is wrong</span></div>
    <div class="card-body pb-1 stl-map">
        <form method="GET" action="{{ route('accounts.settlements.preview') }}" class="form-row align-items-end" id="map-form">
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="col-6 col-md-2 form-group">
                <label>Sheet</label>
                <select name="sheet" class="form-control form-control-sm js-auto">
                    @foreach ($sheets as $name => $n)
                        <option value="{{ $name }}" @selected($name === $sheet)>{{ $name }} ({{ $n }} rows)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1 form-group">
                <label>Header row</label>
                <input type="number" name="header_row" min="0" value="{{ $map['header_row'] ?? 0 }}" class="form-control form-control-sm js-auto">
            </div>
            @foreach (['name' => 'Customer / Zone', 'payment' => 'Total Payment', 'deduction' => 'Deduction'] as $key => $label)
                <div class="col-6 col-md-2 form-group">
                    <label>{{ $label }}</label>
                    <select name="{{ $key }}" class="form-control form-control-sm js-auto {{ $map[$key] ? '' : 'is-invalid' }}">
                        <option value="">— choose —</option>
                        @foreach ($cols as $c)
                            <option value="{{ $c }}" @selected($map[$key] === $c)>{{ $colLabel($c) }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-6 col-md-1 form-group">
                <label>bKash %</label>
                <input type="number" name="bkash_percent" step="0.001" min="0" max="10" value="{{ $bkash_percent }}" class="form-control form-control-sm js-auto">
            </div>
            <div class="col-6 col-md-2 form-group">
                <label>Blank deduction</label>
                <input type="hidden" name="blank_as_zero" value="0">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input js-auto" id="bz" name="blank_as_zero" value="1" @checked($blank_as_zero)>
                    <label class="custom-control-label small" for="bz">Count as 0</label>
                </div>
            </div>
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="cycle" value="{{ $cycle }}">
            <input type="hidden" name="invoice_date" value="{{ $invoice_date }}">
        </form>
    </div>
</div>

{{-- Summary --}}
<div class="acct-stats">
    <div class="acct-stat" style="--accent:#4f46e5">
        <div class="acct-stat-label">Zones counted <i class="fas fa-check-circle"></i></div>
        <div class="acct-stat-value">{{ $totals['count'] }}</div>
        <div class="acct-stat-foot">{{ $rowsOut->count() }} rows read</div>
    </div>
    <div class="acct-stat" style="--accent:#e11d48">
        <div class="acct-stat-label">Not counted <i class="fas fa-times-circle"></i></div>
        <div class="acct-stat-value {{ $errors_->count() ? 'text-danger' : '' }}">{{ $errors_->count() }}</div>
        <div class="acct-stat-foot">{{ $errors_->count() ? 'errors — see the rows in red' : 'no errors' }}{{ $skipped->count() ? ' · ' . $skipped->count() . ' skipped' : '' }}</div>
    </div>
    <div class="acct-stat" style="--accent:#d97706">
        <div class="acct-stat-label">To check <i class="fas fa-exclamation-triangle"></i></div>
        <div class="acct-stat-value">{{ $warns->count() }}</div>
        <div class="acct-stat-foot">counted, with a warning</div>
    </div>
    <div class="acct-stat" style="--accent:#16a34a">
        <div class="acct-stat-label">Company Income <i class="fas fa-coins"></i></div>
        <div class="acct-stat-value money">{{ Dec::taka($totals['income']) }}</div>
        <div class="acct-stat-foot">from {{ Dec::taka($totals['payment']) }} collected</div>
    </div>
</div>

@if ($result['reconcile'])
    @php $allOk = collect($result['reconcile'])->every('ok'); @endphp
    <div class="alert {{ $allOk ? 'alert-success' : 'alert-warning' }}">
        <strong><i class="fas {{ $allOk ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i> Check against the sheet's own Total row:</strong>
        @foreach ($result['reconcile'] as $c)
            <span class="ml-2">{{ $c['label'] }} — sheet {{ Dec::taka($c['sheet']) }}, counted {{ Dec::taka($c['ours']) }} {!! $c['ok'] ? '✔' : '<strong>✘ differs by ' . e(Dec::taka(Dec::sub($c['ours'], $c['sheet']), true)) . '</strong>' !!}</span>
        @endforeach
    </div>
@endif

{{-- Every row --}}
<div class="card acct-panel">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-table mr-1 text-primary"></i> Customer-wise calculation</h3>
        <span class="small text-muted">Grey rows are not counted</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 stl-table">
                <thead>
                    <tr>
                        <th class="pl-3">Row</th>
                        <th>Customer/Zone</th>
                        <th class="text-right">Total Payment</th>
                        <th class="text-right">Deduction</th>
                        <th class="text-right">Total Payable</th>
                        <th class="text-right">Payment Difference</th>
                        <th class="text-right">bKash ({{ rtrim(rtrim($bkash_percent, '0'), '.') }}%)</th>
                        <th class="text-right">Net Bill</th>
                        <th>Checks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rowsOut as $r)
                        <tr class="{{ $r['included'] ? '' : 'out' }}">
                            <td class="pl-3 text-muted">{{ $r['source_row'] }}</td>
                            <td>
                                @php [$zoneName, $zoneUser] = \App\Services\SettlementImporter::splitName($r['name']); @endphp
                                {{ $zoneName ?: '—' }}
                                @if ($zoneUser)<span class="small text-muted">({{ $zoneUser }})</span>@endif
                                @if ($r['customer_id'])<i class="fas fa-link text-success small ml-1" title="Matches a customer in Accounts"></i>@endif
                            </td>
                            <td class="text-right money">{{ $r['total_payment'] !== null ? Dec::taka($r['total_payment']) : (is_scalar($r['raw_payment']) ? $r['raw_payment'] : '') }}</td>
                            <td class="text-right money">{{ $r['deduction'] !== null ? Dec::taka($r['deduction']) : (is_scalar($r['raw_deduction']) ? $r['raw_deduction'] : '') }}</td>
                            @if ($r['calc'])
                                <td class="text-right money font-weight-bold">{{ Dec::taka($r['calc']['invoice']) }}</td>
                                <td class="text-right money">{{ Dec::taka($r['calc']['difference']) }}</td>
                                <td class="text-right money">{{ Dec::taka($r['calc']['bkash']) }}</td>
                                <td class="text-right money font-weight-bold text-success">{{ Dec::taka($r['calc']['income']) }}</td>
                            @else
                                <td colspan="4" class="text-center small">not counted</td>
                            @endif
                            <td class="flags">
                                @foreach ($r['flags'] as [$level, $text])
                                    <span class="stl-flag"><span class="badge {{ $flagIcon[$level][0] }}"><i class="{{ $flagIcon[$level][1] }}"></i></span> {{ $text }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold" style="background:#eef2ff">
                        <td class="pl-3" colspan="2">Grand Total — {{ $totals['count'] }} zones</td>
                        <td class="text-right money">{{ Dec::taka($totals['payment']) }}</td>
                        <td class="text-right money">{{ Dec::taka($totals['deduction']) }}</td>
                        <td class="text-right money">{{ Dec::taka($totals['invoice']) }}</td>
                        <td class="text-right money">{{ Dec::taka($totals['difference']) }}</td>
                        <td class="text-right money">{{ Dec::taka($totals['bkash']) }}</td>
                        <td class="text-right money text-success">{{ Dec::taka($totals['income']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Would count twice --}}
@if ($sameFile)
    <div class="alert alert-danger"><i class="fas fa-copy"></i> <strong>This exact file is already saved</strong> — as the {{ $sameFile->month->format('F Y') }} {{ $sameFile->cycleLabel() }} settlement ({{ $sameFile->source_name }}). Saving it again would count it twice, so it won't be saved.</div>
@endif
@if ($overlap)
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> <strong>{{ count($overlap) }} {{ Str::plural('zone', count($overlap)) }} already settled for this month and group:</strong>
        {{ collect($overlap)->map(fn ($o) => $o['name'] . ' (' . $o['in'] . ')')->implode(', ') }}.
        Take them out of the Excel, or delete that settlement first — otherwise this can't be saved.
    </div>
@endif

{{-- Save --}}
<div class="card acct-panel">
    <form method="POST" action="{{ route('accounts.settlements.store') }}" class="card-body">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="sheet" value="{{ $sheet }}">
        <input type="hidden" name="header_row" value="{{ $map['header_row'] ?? 0 }}">
        <input type="hidden" name="name" value="{{ $map['name'] }}">
        <input type="hidden" name="payment" value="{{ $map['payment'] }}">
        <input type="hidden" name="deduction" value="{{ $map['deduction'] }}">
        <input type="hidden" name="bkash_percent" value="{{ $bkash_percent }}">
        <input type="hidden" name="blank_as_zero" value="{{ $blank_as_zero ? 1 : 0 }}">
        <div class="form-row align-items-end">
            <div class="col-6 col-md-2 form-group">
                <label class="small font-weight-bold">Billing month</label>
                <input type="month" name="month" value="{{ old('month', $month) }}" class="form-control {{ $month ? '' : 'is-invalid' }}" required>
            </div>
            <div class="col-6 col-md-2 form-group">
                <label class="small font-weight-bold">Invoice date</label>
                <input type="date" name="invoice_date" value="{{ old('invoice_date', $invoice_date) }}" class="form-control" required>
            </div>
            <div class="col-md-2 form-group">
                <label class="small font-weight-bold">Group / cycle</label>
                <select name="cycle" class="form-control" required>
                    @foreach (\App\Models\ZoneSettlement::CYCLES as $key => $cy)
                        <option value="{{ $key }}" @selected(old('cycle', $cycle) === $key)>{{ $cy['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label class="small font-weight-bold">Note (optional)</label>
                <input type="text" name="notes" maxlength="500" class="form-control" placeholder="optional">
            </div>
            <div class="col-md-4 form-group">
                <button class="btn btn-success btn-block" @disabled(! $totals['count'])
                        onclick="return confirm('Save this settlement and create {{ $totals['count'] }} invoices ({{ $firstNo }} – {{ $lastNo }})?')">
                    <i class="fas fa-save"></i> Save &amp; create {{ $totals['count'] }} invoices
                </button>
                <small class="d-block text-muted text-center mt-1">Numbered {{ $firstNo }}{{ $totals['count'] > 1 ? ' – ' . $lastNo : '' }}</small>
            </div>
        </div>
        @if ($errors_->count())
            <p class="small text-danger mb-0"><i class="fas fa-info-circle"></i> {{ $errors_->count() }} {{ \Illuminate\Support\Str::plural('row', $errors_->count()) }} with errors will not be saved as invoices — fix them in the Excel and upload again if they should count.</p>
        @endif
    </form>
</div>

<script>
document.querySelectorAll('#map-form .js-auto').forEach(function (el) {
    el.addEventListener('change', function () { document.getElementById('map-form').submit(); });
});
</script>

@stop
