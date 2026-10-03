@extends('adminlte::page')

@section('title', 'Office ID Cards')

@php
    $statusInfo = ['valid' => ['Valid', '#16a34a'], 'expired' => ['Expired', '#d97706'], 'revoked' => ['Cancelled', '#dc2626'], 'left' => ['Employee left', '#dc2626']];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp

@section('css')
<style>
    .idx-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: .9rem; }
    .idx-tile { display: flex; flex-direction: column; align-items: center; gap: .3rem; padding: 1.1rem .9rem .9rem; border: 1px solid #eef2f7; border-radius: 1rem; background: #fff; text-align: center; }
    .idx-tile.left { opacity: .6; }
    .idx-photo { width: 4.2rem; height: 4.2rem; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 0 0 2px #1e88e5; }
    .idx-ph { display: grid; place-items: center; width: 4.2rem; height: 4.2rem; border-radius: 50%; background: #e2e8f0; color: #64748b; font-weight: 800; font-size: 1.2rem; }
    .idx-name { font-weight: 700; color: #0f172a; }
    .idx-sub { font-size: .8rem; color: #64748b; }
    .card-photo { width: 2.3rem; height: 2.3rem; border-radius: 50%; object-fit: cover; }
    .card-ph { display: inline-grid; place-items: center; width: 2.3rem; height: 2.3rem; border-radius: 50%; background: #e2e8f0; color: #64748b; font-weight: 800; font-size: .75rem; }
    .swatch { display: inline-block; width: 1rem; height: 1rem; border-radius: 50%; vertical-align: middle; }
    .sig-preview { max-height: 3.4rem; max-width: 100%; }
</style>
@stop

@section('content_header')
<x-attendance.header title="Office ID Cards" icon="fas fa-id-card" subtitle="Make an ID card for anyone — employees, interns, partners or guests. Standard size 85.6 × 54 mm">
    <a href="{{ route('attendance.idcards.make') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New card for anyone</a>
</x-attendance.header>
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="row">
    <div class="col-xl-8">
        {{-- Cards made so far --}}
        <div class="card acct-panel">
            <div class="card-header flex-wrap" style="gap:.5rem">
                <h3 class="card-title"><i class="fas fa-id-card mr-1 text-primary"></i> Cards issued</h3>
                <button type="submit" form="sheet-form" class="btn btn-sm btn-primary" id="sheet-btn" disabled>
                    <i class="fas fa-print"></i> {{ \App\Support\Ui::t('Print selected on A4') }} <span class="badge badge-light" id="sheet-count">0</span>
                </button>
                <form method="GET" class="form-inline" style="gap:.4rem">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name or card no">
                    <button class="btn btn-sm btn-light border"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table inv-table mb-0">
                        <thead><tr><th class="pl-3" style="width:2rem"><input type="checkbox" id="sheet-all" title="Select all"></th><th>Holder</th><th>Card no</th><th>Issued</th><th>Expires</th><th>Status</th><th class="pr-3"></th></tr></thead>
                        <tbody>
                            @forelse ($cards as $card)
                                @php [$sLabel, $sColor] = $statusInfo[$card->status()]; @endphp
                                <tr>
                                    <td class="pl-3"><input type="checkbox" name="cards[]" value="{{ $card->id }}" form="sheet-form" class="sheet-pick"></td>
                                    <td>
                                        <div class="d-flex align-items-center" style="gap:.6rem">
                                            @if ($card->photo)
                                                <img src="{{ route('attendance.idcards.photo', $card) }}" class="card-photo" alt="">
                                            @else
                                                <span class="card-ph">{{ $initials($card->name) }}</span>
                                            @endif
                                            <div>
                                                <a href="{{ route('attendance.idcards.make', ['card' => $card->id]) }}" class="inv-name">{{ $card->name }}</a>
                                                <div class="inv-sub">{{ $card->designation ?: '—' }}{{ $card->employee_id ? '' : ' · ' . \App\Support\Ui::t('not an employee') }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small text-nowrap">{{ $card->card_no }}@if ($card->id_no)<div class="inv-sub">{{ $card->id_no }}</div>@endif</td>
                                    <td class="small text-nowrap">{{ $card->issue_date->format('d M Y') }}</td>
                                    <td class="small text-nowrap {{ $card->status() === 'expired' ? 'text-warning font-weight-bold' : '' }}">{{ $card->expiry_date?->format('d M Y') ?? '—' }}</td>
                                    <td><span class="wk-pill" style="--c: {{ $sColor }}">{{ \App\Support\Ui::t($sLabel) }}</span></td>
                                    <td class="pr-3 text-right text-nowrap">
                                        <a href="{{ route('attendance.idcards.make', ['card' => $card->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-id-card"></i> Open</a>
                                        @if (auth()->user()->isAdmin())
                                            <form method="POST" action="{{ route('attendance.idcards.destroy', $card) }}" class="d-inline js-confirm-delete" data-confirm-message="Remove this card from the list? Its QR will stop working.">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="acct-empty"><i class="fas fa-id-card"></i>No cards yet — pick an employee below, or make one for anyone.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">
                <i class="fas fa-info-circle"></i> Tick the cards to print: A4 landscape, 5 cards a page — fronts on top, each back below — with dashed cut lines.
            </div>
            @if ($cards->hasPages())
                <div class="card-footer">{{ $cards->links() }}</div>
            @endif
        </div>
        <form id="sheet-form" method="GET" action="{{ route('attendance.idcards.sheet') }}" target="_blank"></form>

        {{-- Employees: one click to a card --}}
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users mr-1 text-primary"></i> Employees</h3>
                <span class="small text-muted">{{ $employees->where('status', true)->count() }} active</span>
            </div>
            <div class="card-body">
                @if ($employees->isEmpty())
                    <div class="acct-empty"><i class="fas fa-id-badge"></i>No employees yet — add them in <a href="{{ route('attendance.employees.index') }}">Employees</a>, or make a card for anyone.</div>
                @endif
                <div class="idx-grid">
                    @foreach ($employees as $e)
                        @php $last = $e->idCards->first(); @endphp
                        <div @class(['idx-tile', 'left' => $e->hasLeft()])>
                            @if ($e->photoUrl())
                                <img src="{{ $e->photoUrl() }}" class="idx-photo" alt="">
                            @else
                                <span class="idx-ph">{{ $initials($e->name) }}</span>
                            @endif
                            <div class="idx-name">{{ $e->name }}</div>
                            <div class="idx-sub">{{ $e->designation ?: '—' }} · {{ $e->empId() }}</div>
                            <div class="mb-1">
                                @if ($e->hasLeft())
                                    <span class="wk-pill" style="--c:#dc2626">{{ \App\Support\Ui::t('Left') }}</span>
                                @elseif ($last)
                                    <span class="wk-pill" style="--c: {{ $statusInfo[$last->status()][1] }}">{{ $last->card_no }} · {{ \App\Support\Ui::t($statusInfo[$last->status()][0]) }}</span>
                                @else
                                    <span class="wk-pill" style="--c:#64748b">{{ \App\Support\Ui::t('No card yet') }}</span>
                                @endif
                            </div>
                            @if ($last)
                                <a href="{{ route('attendance.idcards.make', ['card' => $last->id]) }}" class="btn btn-sm btn-outline-primary btn-block"><i class="fas fa-id-card"></i> Open card</a>
                                <a href="{{ route('attendance.idcards.make', ['employee' => $e->id]) }}" class="small">New card</a>
                            @else
                                <a href="{{ route('attendance.idcards.make', ['employee' => $e->id]) }}" class="btn btn-sm btn-primary btn-block"><i class="fas fa-plus"></i> Make ID card</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-signature mr-1 text-primary"></i> Authorized signature</h3></div>
            <div class="card-body text-center">
                @if ($signature)
                    <img src="{{ $signature }}" class="sig-preview mb-2" alt="">
                    <div class="small text-muted mb-2">{{ $settings['signatory'] }}, {{ $settings['signatory_title'] }}</div>
                @else
                    <div class="text-danger small mb-2"><i class="fas fa-exclamation-circle"></i> No signature yet — cards and letters print a blank line.</div>
                @endif
                <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#sig-modal"><i class="fas fa-pen-nib"></i> {{ $signature ? \App\Support\Ui::t('Change signature') : \App\Support\Ui::t('Draw or upload signature') }}</button>
            </div>
        </div>

        <form method="POST" action="{{ route('attendance.idcards.settings') }}" class="card acct-panel" id="idc-settings">
            @csrf @method('PUT')
            <div class="card-header"><h3 class="card-title"><i class="fas fa-sliders-h mr-1 text-primary"></i> Card back &amp; defaults</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Office phone</label>
                    <input type="text" name="office_phone" value="{{ old('office_phone', $settings['office_phone']) }}" class="form-control" maxlength="50">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" name="email" value="{{ old('email', $settings['email']) }}" class="form-control" maxlength="100">
                </div>
                <div class="form-group">
                    <label>Website</label>
                    <input type="text" name="website" value="{{ old('website', $settings['website']) }}" class="form-control" maxlength="100">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2" class="form-control" maxlength="255">{{ old('address', $settings['address']) }}</textarea>
                </div>
                <div class="form-row">
                    <div class="col-7 form-group">
                        <label>Default template</label>
                        <select name="theme" class="form-control">
                            @foreach ($themes as $k => [$tLabel, $d1, $d2, $d3])
                                <option value="{{ $k }}" @selected($settings['theme'] === $k)>{{ \App\Support\Ui::t($tLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5 form-group">
                        <label>Valid for (years)</label>
                        <input type="number" name="validity_years" value="{{ old('validity_years', $settings['validity_years']) }}" min="1" max="10" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col-7 form-group mb-0">
                        <label>Signed by</label>
                        <input type="text" name="signatory" value="{{ old('signatory', $settings['signatory']) }}" class="form-control" maxlength="100">
                    </div>
                    <div class="col-5 form-group mb-0">
                        <label>Title</label>
                        <input type="text" name="signatory_title" value="{{ old('signatory_title', $settings['signatory_title']) }}" class="form-control" maxlength="100">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

@include('attendance.idcards.partials.signature-pad', ['hasSignature' => (bool) $signature])

@stop

@section('js')
<script>
(function () {
    var picks = document.querySelectorAll('.sheet-pick'), btn = document.getElementById('sheet-btn'), count = document.getElementById('sheet-count');
    function update() { var n = document.querySelectorAll('.sheet-pick:checked').length; count.textContent = n; btn.disabled = n === 0; }
    picks.forEach(function (p) { p.addEventListener('change', update); });
    document.getElementById('sheet-all').addEventListener('change', function () { var on = this.checked; picks.forEach(function (p) { p.checked = on; }); update(); });
})();
</script>
@stop
