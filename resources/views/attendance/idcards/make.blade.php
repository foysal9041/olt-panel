@extends('adminlte::page')

@section('title', 'Office ID Card')

@use('App\Models\Employee')
@use('App\Services\HrDocs')
@php
    $saved = $card->exists;
    $up = fn ($d) => $d ? $d->format('d M Y') : '';
    $c = [
        'name' => old('name', $card->name),
        'designation' => old('designation', $card->designation),
        'department' => old('department', $card->department),
        'id_label' => $card->employee_id ? 'Emp ID' : 'ID No',
        'id_no' => old('id_no', $card->id_no),
        'joining_date' => $up($card->joining_date),
        'blood_group' => old('blood_group', $card->blood_group),
        'phone' => old('phone', $card->phone),
        'expiry' => $up($card->expiry_date),
        'card_no' => $card->card_no,
        'photo' => $photo,
        'qr' => $saved ? $card->verifyUrl() : '',
    ];
    $themeKey = old('theme', $card->theme ?: $settings['theme']);
    $logo = asset('images/logo.png') . '?v=' . filemtime(public_path('images/logo.png'));
    $fileName = trim(preg_replace('/[^\w\s.-]+/u', ' ', 'ID Card ' . ($c['name'] ?: 'new') . ($card->card_no ? ' ' . $card->card_no : '')));
    $status = $saved ? $card->status() : null;
    $statusInfo = ['valid' => ['Valid', '#16a34a'], 'expired' => ['Expired', '#d97706'], 'revoked' => ['Cancelled', '#dc2626'], 'left' => ['Employee left', '#dc2626']];
@endphp

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
@include('components.print.id-card-style')
<style>
    .idc-stage { display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-start; gap: 1.75rem; padding: 1.5rem .5rem; background: repeating-conic-gradient(#f1f5f9 0 25%, #f8fafc 0 50%) 0 0 / 22px 22px; border-radius: .8rem; }
    .idc-slot { display: flex; flex-direction: column; align-items: center; }
    .idc-scale { --s: 1.5; width: calc(53.98mm * var(--s)); height: calc(85.6mm * var(--s)); }
    .idc-scale > .idc { transform: scale(var(--s)); transform-origin: top left; box-shadow: 0 1px 3px rgba(15, 23, 42, .2), 0 10px 26px -12px rgba(15, 23, 42, .35); }
    .idc-cap { margin-top: .6rem; font-size: .75rem; font-weight: 700; letter-spacing: .12em; color: #64748b; }
    .idc-crop { display: none; }
    .idc-crop.on { display: block; }
    .idc-crop label { font-size: .78rem; margin-bottom: 0; color: #64748b; }
    .idc-zoom .btn.active { background: #4f46e5; color: #fff; border-color: #4f46e5; }
    .idc-size { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .65rem; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: .78rem; font-weight: 700; }
    .sig-mini { height: 2.6rem; max-width: 10rem; object-fit: contain; }
    .idp { display: flex; flex-direction: column; gap: .45rem; font-size: .82rem; }
    .idp-row { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .6rem; }
    .idp-k { font-weight: 700; color: #475569; min-width: 4.5rem; }
    .idp-pill { margin: 0; cursor: pointer; }
    .idp-pill input { display: none; }
    .idp-pill span { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .7rem; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; color: #475569; font-weight: 600; }
    .idp-pill input:checked + span { border-color: #4f46e5; background: #eef2ff; color: #4338ca; }
    .idp-sel { width: auto; max-width: 100%; height: calc(1.6em + .5rem + 2px); padding: .2rem .5rem; font-size: .8rem; border: 1px solid #ced4da; border-radius: .3rem; }
    .idp-check { display: inline-flex; align-items: flex-start; gap: .4rem; margin: 0; font-weight: 400; color: #334155; cursor: pointer; }
    .idp-check input { margin-top: .2rem; }
    .tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(78px, 1fr)); gap: .5rem; }
    .tpl { margin: 0; padding: .35rem .3rem .3rem; border: 2px solid #e2e8f0; border-radius: .6rem; background: #fff; cursor: pointer; text-align: center; transition: border-color .15s ease; }
    .tpl:hover { border-color: #a5b4fc; }
    .tpl.on { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, .15); }
    .tpl input { display: none; }
    .tpl svg { display: block; width: 100%; height: auto; border-radius: .35rem; box-shadow: 0 1px 3px rgba(15, 23, 42, .25); }
    .tpl span { display: block; margin-top: .25rem; font-size: .66rem; font-weight: 600; line-height: 1.2; color: #475569; }
</style>
@stop

@section('content_header')
<x-attendance.header title="Office ID Card" icon="fas fa-id-card" :back="route('attendance.idcards.index')"
    subtitle="For anyone — an employee or not. Standard ID card size (CR80): 85.6 × 54 mm" />
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="row">
    <div class="col-xl-5">
        <form method="POST" action="{{ $saved ? route('attendance.idcards.update', $card) : route('attendance.idcards.store') }}" id="idc-form" class="card acct-panel">
            @csrf
            @if ($saved) @method('PUT') @endif
            <input type="hidden" name="photo" id="idc-photo-data">
            <input type="hidden" name="theme" id="idc-theme-input" value="{{ $themeKey }}">
            @if ($card->employee_id)<input type="hidden" name="employee_id" value="{{ $card->employee_id }}">@endif
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit mr-1 text-primary"></i> Card details</h3>
                @if ($saved)
                    <span class="wk-pill" style="--c: {{ $statusInfo[$status][1] }}">{{ $card->card_no }} · {{ \App\Support\Ui::t($statusInfo[$status][0]) }}</span>
                @endif
            </div>
            <div class="card-body">
                @unless ($saved)
                    <div class="form-group">
                        <label>Whose card?</label>
                        <select id="idc-employee" class="form-control">
                            <option value="">Anyone — type the details below</option>
                            <optgroup label="{{ \App\Support\Ui::t('Employees') }}">
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" @selected($card->employee_id === $emp->id)>{{ $emp->name }}{{ $emp->designation ? ' — ' . $emp->designation : '' }}{{ $emp->status ? '' : ' (left)' }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        <small class="form-text text-muted">Pick an employee to fill in their details, or keep "Anyone" for a guest, intern, partner or contractor.</small>
                    </div>
                @else
                    <p class="small text-muted">
                        {{ $card->employee ? \App\Support\Ui::t('Employee') . ': ' . $card->employee->name : \App\Support\Ui::t('Not an employee') }}
                        · {{ \App\Support\Ui::t('made') }} {{ $card->created_at->format('d M Y') }}@if ($card->creator) · {{ $card->creator->name }}@endif
                    </p>
                @endunless

                <div class="form-group">
                    <label>Photo</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="idc-file" accept="image/*">
                        <label class="custom-file-label" for="idc-file">{{ $photo ? \App\Support\Ui::t('Change photo…') : \App\Support\Ui::t('Choose a photo…') }}</label>
                    </div>
                    <small class="form-text text-muted">A clear face photo on a plain background works best. Use the sliders to fit the face in the circle.</small>
                    <div class="idc-crop mt-2" id="idc-crop">
                        <label>Zoom</label><input type="range" class="custom-range" id="idc-zoom" min="1" max="3" step="0.01" value="1">
                        <label>Left / right</label><input type="range" class="custom-range" id="idc-x" min="-1" max="1" step="0.01" value="0">
                        <label>Up / down</label><input type="range" class="custom-range" id="idc-y" min="-1" max="1" step="0.01" value="-0.35">
                    </div>
                </div>

                <div class="form-row">
                    <div class="col-md-12 form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" data-bind="name" value="{{ $c['name'] }}" maxlength="255" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" class="form-control" data-bind="designation" value="{{ $c['designation'] }}" maxlength="255" placeholder="e.g. System Administrator">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control" data-bind="department" value="{{ $c['department'] }}" maxlength="255" list="idc-depts" placeholder="e.g. IT & Network">
                        <datalist id="idc-depts">@foreach (Employee::DEPARTMENTS as $d)<option value="{{ $d }}">@endforeach</datalist>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>{{ $card->employee_id ? \App\Support\Ui::t('Emp ID') : \App\Support\Ui::t('ID No') }}</label>
                        <input type="text" name="id_no" class="form-control" data-bind="id" value="{{ $c['id_no'] }}" maxlength="30" placeholder="SNDC-001">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" data-bind="phone" value="{{ $c['phone'] }}" maxlength="50" placeholder="01XXX-XXXXXX">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Blood group</label>
                        <select name="blood_group" class="form-control" data-bind="blood_group">
                            <option value="">—</option>
                            @foreach (Employee::BLOOD_GROUPS as $bg)
                                <option value="{{ $bg }}" @selected($c['blood_group'] === $bg)>{{ $bg }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Join date</label>
                        <input type="date" name="joining_date" class="form-control" data-bind="joining_date" data-date value="{{ old('joining_date', $card->joining_date?->toDateString()) }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Issue date <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', $card->issue_date?->toDateString()) }}" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Expiry date</label>
                        <input type="date" name="expiry_date" class="form-control" data-bind="expiry" data-date value="{{ old('expiry_date', $card->expiry_date?->toDateString()) }}">
                    </div>
                    <div class="col-md-12 form-group">
                        <label>Template</label>
                        <div class="tpl-grid" id="idc-templates">
                            @foreach ($themes as $k => [$tLabel, $d1, $d2, $d3, $tLayout])
                                <label class="tpl {{ $k === $themeKey ? 'on' : '' }}" title="{{ \App\Support\Ui::t($tLabel) }}">
                                    <input type="radio" name="tpl" value="{{ $k }}" data-c="{{ $d1 }},{{ $d2 }},{{ $d3 }}" data-layout="{{ $tLayout }}" @checked($k === $themeKey)>
                                    @include('attendance.idcards.partials.template-thumb', ['l' => $tLayout, 'd' => $d1, 'm' => $d2, 'a' => $d3])
                                    <span>{{ \App\Support\Ui::t($tLabel) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($card->employee_id)
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="idc-to-emp" name="save_to_employee" value="1" checked>
                        <label class="custom-control-label font-weight-normal" for="idc-to-emp">Also keep these details and the photo on {{ $card->employee?->name }}'s employee record</label>
                    </div>
                @endif
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-primary btn-block"><i class="fas fa-save"></i> {{ $saved ? \App\Support\Ui::t('Save card') : \App\Support\Ui::t('Save & issue card') }}</button>
                <small class="form-text text-muted text-center">
                    @if ($saved)
                        The QR on the back checks this card: valid, expired or cancelled.
                    @else
                        Saving gives the card its number, and its QR then shows whether the card is valid.
                    @endif
                </small>
            </div>
        </form>

        @if ($saved)
            <div class="d-flex justify-content-between align-items-center mb-3">
                <form method="POST" action="{{ route('attendance.idcards.revoke', $card) }}" class="js-confirm-delete"
                      data-confirm-message="{{ $card->revoked_at ? \App\Support\Ui::t('Make this card valid again?') : \App\Support\Ui::t('Cancel this card? Its QR will show NOT VALID (lost or returned card).') }}">
                    @csrf
                    <button class="btn btn-sm {{ $card->revoked_at ? 'btn-outline-success' : 'btn-outline-danger' }}">
                        <i class="fas {{ $card->revoked_at ? 'fa-undo' : 'fa-ban' }}"></i> {{ $card->revoked_at ? \App\Support\Ui::t('Make valid again') : \App\Support\Ui::t('Cancel card (lost / returned)') }}
                    </button>
                </form>
                <a href="{{ $card->verifyUrl() }}" target="_blank" class="small"><i class="fas fa-qrcode"></i> Open the QR page</a>
            </div>
        @endif

        @if ($earlier->isNotEmpty())
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Other cards of {{ $card->employee?->name }}</h3></div>
                <div class="card-body p-0">
                    @foreach ($earlier as $e)
                        <a href="{{ route('attendance.idcards.make', ['card' => $e->id]) }}" class="acct-list-row">
                            <div class="acct-list-main">
                                <div class="acct-list-title">{{ $e->card_no }}</div>
                                <div class="acct-list-sub">{{ $e->issue_date->format('d M Y') }} → {{ $e->expiry_date?->format('d M Y') ?? '—' }}</div>
                            </div>
                            <span class="wk-pill" style="--c: {{ $statusInfo[$e->status()][1] }}">{{ \App\Support\Ui::t($statusInfo[$e->status()][0]) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-7">
        <div class="card acct-panel">
            <div class="card-header flex-wrap" style="gap:.5rem">
                <h3 class="card-title"><i class="fas fa-eye mr-1 text-primary"></i> Preview <span class="idc-size ml-2"><i class="fas fa-ruler-combined"></i> 85.6 × 54 mm · CR80</span></h3>
                <div class="d-flex flex-wrap align-items-center" style="gap:.35rem">
                    <button type="button" class="btn btn-primary btn-sm" id="idc-print"><i class="fas fa-print"></i> Print</button>
                    <button type="button" class="btn btn-success btn-sm" id="idc-pdf"><i class="fas fa-file-pdf"></i> Download PDF</button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="idc-png"><i class="fas fa-image"></i> PNG</button>
                </div>
            </div>
            <div class="card-body border-bottom py-2">
                @include('attendance.idcards.partials.print-options', ['copies' => true])
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2" style="gap:.5rem">
                    <div class="btn-group btn-group-sm idc-zoom">
                        <button type="button" class="btn btn-light border" data-zoom="1" title="Real size on a normal screen">100%</button>
                        <button type="button" class="btn btn-light border active" data-zoom="1.5">150%</button>
                        <button type="button" class="btn btn-light border" data-zoom="2">200%</button>
                    </div>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($signature)
                            <img src="{{ $signature }}" class="sig-mini" alt="" style="background:#0a2a5e; border-radius:.4rem; padding:.2rem .4rem">
                        @else
                            <span class="small text-danger"><i class="fas fa-exclamation-circle"></i> No signature yet</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#sig-modal"><i class="fas fa-signature"></i> {{ $signature ? \App\Support\Ui::t('Change signature') : \App\Support\Ui::t('Add signature') }}</button>
                    </div>
                </div>
                <div class="idc-stage" id="idc-preview">
                    @include('components.print.id-card', ['c' => $c, 's' => $settings, 't' => $themes[$themeKey] ?? HrDocs::THEMES['navy'], 'logo' => $logo, 'signature' => $signature])
                </div>
                <p class="small text-muted mt-3 mb-0">
                    <i class="fas fa-info-circle"></i>
                    <b>A4 paper</b>: landscape, up to 5 cards a page — fronts on top, each back below — with dashed cut lines.
                    <b>PVC card printer</b>: one card per page, exactly 85.6 × 54 mm; choose portrait or landscape to match the printer driver, and "all fronts, then all backs" for a one-sided printer (print the fronts, put the cards back in turned over, print the backs).
                    <b>Mirror</b> reverses the picture for transfer paper or clear film. Always print at 100% / actual size.
                    The back side and colour are set on the <a href="{{ route('attendance.idcards.index') }}#idc-settings">ID Cards page</a>.
                </p>
            </div>
        </div>
    </div>
</div>

@include('attendance.idcards.partials.signature-pad', ['hasSignature' => (bool) $signature])

@stop

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
        integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('js/idcard-sheet.js') }}?v={{ filemtime(public_path('js/idcard-sheet.js')) }}"></script>
<script>
(function () {
    var stage = document.getElementById('idc-preview');
    var cards = function () { return stage.querySelectorAll('.idc'); };
    var front = function () { return stage.querySelector('.idc-front'); };
    var fileName = @json($fileName);
    var savedQr = @json($c['qr']);

    // Each side scaled for the screen, labelled.
    Array.prototype.forEach.call(cards(), function (card, i) {
        var slot = document.createElement('div'); slot.className = 'idc-slot';
        var scale = document.createElement('div'); scale.className = 'idc-scale';
        card.parentNode.insertBefore(slot, card);
        scale.appendChild(card); slot.appendChild(scale);
        var cap = document.createElement('div'); cap.className = 'idc-cap'; cap.textContent = i === 0 ? @json(\App\Support\Ui::t('FRONT')) : @json(\App\Support\Ui::t('BACK'));
        slot.appendChild(cap);
    });
    document.querySelectorAll('[data-zoom]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('[data-zoom]').forEach(function (x) { x.classList.toggle('active', x === b); });
            stage.querySelectorAll('.idc-scale').forEach(function (s) { s.style.setProperty('--s', b.dataset.zoom); });
        });
    });

    // ---- Text fields ----------------------------------------------------------
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    function fit(el) {
        el.style.fontSize = '';
        var size = parseFloat(getComputedStyle(el).fontSize), min = size * 0.6;
        while (el.scrollWidth > el.clientWidth + 0.5 && size > min) { size -= 0.2; el.style.fontSize = size + 'px'; }
    }
    function val(f) { var i = document.querySelector('[data-bind="' + f + '"]'); return i ? i.value.trim() : ''; }
    function sync() {
        document.querySelectorAll('[data-bind]').forEach(function (input) {
            var v = input.value.trim();
            if (input.hasAttribute('data-date') && v) { var p = v.split('-'); v = p[2] + ' ' + months[parseInt(p[1], 10) - 1] + ' ' + p[0]; }
            front().querySelectorAll('[data-f="' + input.dataset.bind + '"]').forEach(function (el) { el.textContent = v; });
            var row = front().querySelector('.idc-info [data-row="' + input.dataset.bind + '"]');
            if (row) row.style.display = v ? '' : 'none';
        });
        fit(front().querySelector('.idc-name'));
        fit(front().querySelector('.idc-role'));
        var ph = front().querySelector('[data-photo-ph]');
        if (ph) ph.textContent = (val('name').split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0); }).join('') || '?').toUpperCase();
        if (!savedQr) qr();
    }
    document.querySelectorAll('[data-bind]').forEach(function (i) { i.addEventListener('input', sync); i.addEventListener('change', sync); });

    // ---- QR: the card's check page once saved, else the details as text ------
    var qrTimer = null;
    function qr() {
        clearTimeout(qrTimer);
        qrTimer = setTimeout(function () {
            var box = stage.querySelector('[data-qr]');
            // The QR library only copes with plain ASCII text.
            var text = (savedQr || ['Sunlit Network DC - ID card', 'Name: ' + val('name'), 'ID: ' + val('id'), 'Phone: ' + val('phone'), 'Valid till: ' + val('expiry')].join('\n'))
                .replace(/[^\x20-\x7E\n]/g, '');
            var tmp = document.createElement('div');
            try {
                new QRCode(tmp, { text: text, width: 300, height: 300, colorDark: '#0f172a', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
            } catch (e) {
                tmp.innerHTML = '';
                new QRCode(tmp, { text: text.slice(0, 120), width: 300, height: 300, colorDark: '#0f172a', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.L });
            }
            var img = new Image(); img.alt = ''; img.src = tmp.querySelector('canvas').toDataURL('image/png');
            box.innerHTML = ''; box.appendChild(img);
        }, 150);
    }

    // ---- Colour -----------------------------------------------------------------
    function theme() {
        var pick = document.querySelector('#idc-templates input:checked'), c = pick.dataset.c.split(','), layout = pick.dataset.layout;
        document.getElementById('idc-theme-input').value = pick.value;
        document.querySelectorAll('#idc-templates .tpl').forEach(function (t) { t.classList.toggle('on', t.contains(pick)); });
        Array.prototype.forEach.call(cards(), function (card) {
            card.style.setProperty('--d', c[0]); card.style.setProperty('--m', c[1]); card.style.setProperty('--a', c[2]);
            card.classList.remove('idc-l-wave', 'idc-l-angle', 'idc-l-classic'); card.classList.add('idc-l-' + layout);
        });
        sync();
    }
    document.querySelectorAll('#idc-templates input').forEach(function (i) { i.addEventListener('change', theme); });

    // ---- Photo: square crop with zoom / move ----------------------------------
    var source = null;
    function crop() {
        if (!source) return;
        var zoom = +document.getElementById('idc-zoom').value, dx = +document.getElementById('idc-x').value, dy = +document.getElementById('idc-y').value;
        var side = Math.min(source.width, source.height) / zoom;
        var sx = (source.width - side) / 2 * (1 + dx), sy = (source.height - side) / 2 * (1 + dy);
        var canvas = document.createElement('canvas'); canvas.width = canvas.height = 600;
        var ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, 600, 600);
        ctx.drawImage(source, sx, sy, side, side, 0, 0, 600, 600);
        var url = canvas.toDataURL('image/jpeg', 0.9);
        var img = front().querySelector('[data-photo]'); img.src = url; img.style.display = '';
        var ph = front().querySelector('[data-photo-ph]'); if (ph) ph.style.display = 'none';
        document.getElementById('idc-photo-data').value = url;
    }
    document.getElementById('idc-file').addEventListener('change', function () {
        var f = this.files[0]; if (!f) return;
        this.nextElementSibling.textContent = f.name;
        var reader = new FileReader();
        reader.onload = function () { var im = new Image(); im.onload = function () { source = im; document.getElementById('idc-crop').classList.add('on'); crop(); }; im.src = reader.result; };
        reader.readAsDataURL(f);
    });
    ['idc-zoom', 'idc-x', 'idc-y'].forEach(function (id) { document.getElementById(id).addEventListener('input', crop); });

    var pick = document.getElementById('idc-employee');
    if (pick) pick.addEventListener('change', function () {
        location.href = @json(route('attendance.idcards.make')) + (this.value ? '?employee=' + this.value : '');
    });

    // ---- Print / PDF / PNG with the chosen options (public/js/idcard-sheet.js) -----
    var options = IdSheet.bindOptions(document.getElementById('idc-print-opts'));
    var pair = function () { return { front: front(), back: stage.querySelector('.idc-back') }; };
    var pairs = function (o) { var n = o.paper === 'a4' ? o.copies : 1, out = []; for (var i = 0; i < n; i++) out.push(pair()); return out; };
    var suffix = function (o) { return (o.paper === 'a4' ? ' A4' : ' card') + (o.mirror ? ' mirror' : ''); };
    async function busy(btn, job) {
        var label = btn.innerHTML; btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + @json(\App\Support\Ui::t('Preparing…'));
        try { await job(); } catch (e) { alert(@json(\App\Support\Ui::t('Could not make the file')) + ': ' + e.message); }
        finally { btn.disabled = false; btn.innerHTML = label; }
    }
    document.getElementById('idc-pdf').addEventListener('click', function () {
        var b = this, o = options();
        busy(b, function () { return IdSheet.pdf(pairs(o), fileName + suffix(o), o); });
    });
    document.getElementById('idc-png').addEventListener('click', function () {
        var b = this, o = options();
        busy(b, function () { return IdSheet.png(pair(), fileName, o); });
    });
    document.getElementById('idc-print').addEventListener('click', function () {
        var o = options();
        if (!IdSheet.print(pairs(o), fileName, o)) alert(@json(\App\Support\Ui::t('Allow pop-ups for this site to print.')));
    });

    theme();
    sync();
    qr();
})();
</script>
@stop
