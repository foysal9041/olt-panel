@extends('adminlte::page')

@section('title', 'Add OLTs')

@section('content_header')
<x-noc.header title="Add OLTs" subtitle="Add one or several OLTs to a zone at once" :back="route('olt.index')" />
@stop

@php
    $oldRows = old('olts', [[]]);
    if (! $oldRows) { $oldRows = [[]]; }
    $rowErr = fn ($i, $f) => $errors->has("olts.$i.$f") ? ' is-invalid' : '';
@endphp

@section('css')
<style>
    .olt-rows td { vertical-align: middle; }
    .olt-rows .row-no { width: 2.5rem; color: #94a3b8; font-weight: 600; }
    .olt-rows .form-control { min-width: 7rem; }
    .olt-rows .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .suggest-note { font-size: .8rem; color: #64748b; }
</style>
@stop

@section('content')

<form method="POST" action="{{ route('olt.store') }}" id="olt-form">
    @csrf

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-map-marker-alt mr-1"></i> Zone &amp; common settings</h3>
            <div class="card-tools small text-muted">Used for every OLT below</div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 form-group">
                    <label>Zone <span class="text-danger">*</span></label>
                    <select name="zone" id="zone" class="form-control js-zone-select" data-placeholder="Select a zone" required>
                        <option value=""></option>
                        @foreach ($zoneStats as $z => $st)
                            <option value="{{ $z }}" data-name="{{ $z }}" data-count="{{ $st['count'] }}" data-down="{{ $st['down'] }}" data-pop="{{ $st['pop'] ? 1 : 0 }}" @selected(old('zone', $zone) === $z)>{{ $z }}</option>
                        @endforeach
                    </select>
                    @if ($zones->isEmpty())
                        <small class="text-danger">No zones yet — <a href="{{ route('zones.create') }}">add one first</a>.</small>
                    @endif
                </div>
                <div class="col-md-4 form-group">
                    <label>Brand <span class="text-danger">*</span></label>
                    <input type="text" name="brand" class="form-control" value="{{ old('brand') }}" placeholder="BDCOM / VSOL / C-DATA / ZTE / HUAWEI" required>
                </div>
                <div class="col-md-4 form-group">
                    <label>SNMP Community <small class="text-muted">(optional)</small></label>
                    <input type="text" name="snmp" class="form-control" value="{{ old('snmp') }}" placeholder="e.g. public — leave blank if not used">
                </div>
                <div class="col-md-4 form-group mb-md-0">
                    <label>Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" value="{{ old('username') }}" autocomplete="off" required>
                </div>
                <div class="col-md-4 form-group mb-0">
                    <label>Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-success">
        <div class="card-header d-flex flex-wrap align-items-center" style="gap:.5rem">
            <h3 class="card-title mr-auto"><i class="fas fa-network-wired mr-1"></i> OLTs <span class="badge badge-light border ml-1" id="row-count">{{ count($oldRows) }}</span></h3>
            <div class="form-inline" style="gap:.35rem">
                <label class="small text-muted mr-1" for="vlan-size">VLANs per OLT</label>
                <input type="number" id="vlan-size" class="form-control form-control-sm" value="4" min="1" max="64" style="width:4.5rem">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-suggest" title="Fill empty names, IPs and VLANs with the next free ones for this zone">
                    <i class="fas fa-magic"></i> Suggest name, IP &amp; VLAN
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 olt-rows">
                    <thead class="thead-light">
                        <tr>
                            <th class="row-no text-center">#</th>
                            <th>OLT Name <span class="text-danger">*</span></th>
                            <th>IP Address <span class="text-danger">*</span></th>
                            <th>VLAN</th>
                            <th>Brand <small class="text-muted">(if different)</small></th>
                            <th style="width:3rem"></th>
                        </tr>
                    </thead>
                    <tbody id="rows">
                        @foreach ($oldRows as $i => $r)
                            <tr>
                                <td class="row-no text-center">{{ $loop->iteration }}</td>
                                <td><input type="text" name="olts[{{ $i }}][name]" value="{{ $r['name'] ?? '' }}" class="form-control f-name{{ $rowErr($i, 'name') }}" placeholder="e.g. Navaron OLT-9"></td>
                                <td><input type="text" name="olts[{{ $i }}][ip]" value="{{ $r['ip'] ?? '' }}" class="form-control mono f-ip{{ $rowErr($i, 'ip') }}" placeholder="192.168.50.86"></td>
                                <td><input type="text" name="olts[{{ $i }}][vlan]" value="{{ $r['vlan'] ?? '' }}" class="form-control mono f-vlan{{ $rowErr($i, 'vlan') }}" placeholder="1422-1425"></td>
                                <td><input type="text" name="olts[{{ $i }}][brand]" value="{{ $r['brand'] ?? '' }}" class="form-control f-brand" placeholder="same"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-light js-remove" title="Remove row"><i class="fas fa-times text-danger"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap align-items-center" style="gap:.5rem">
            <button type="button" class="btn btn-outline-success btn-sm" id="btn-add"><i class="fas fa-plus"></i> Add row</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="btn-add5"><i class="fas fa-plus"></i> 5 rows</button>
            <span class="suggest-note ml-2" id="suggest-note">
                Pick a zone, add a row per OLT, then <em>Suggest</em> fills the next free IPs (/30, device .2) and VLANs — you can change any of them.
                Duplicates are checked when you save.
            </span>
        </div>
    </div>

    <div class="mb-4">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <span id="save-label">Save OLT</span></button>
        <a href="{{ route('olt.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<template id="row-template">
    <tr>
        <td class="row-no text-center"></td>
        <td><input type="text" data-field="name" class="form-control f-name" placeholder="OLT name"></td>
        <td><input type="text" data-field="ip" class="form-control mono f-ip" placeholder="IP address"></td>
        <td><input type="text" data-field="vlan" class="form-control mono f-vlan" placeholder="VLAN"></td>
        <td><input type="text" data-field="brand" class="form-control f-brand" placeholder="same"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-light js-remove" title="Remove row"><i class="fas fa-times text-danger"></i></button></td>
    </tr>
</template>

<script>
$(function () {

    var tbody = document.getElementById('rows');
    var tpl = document.getElementById('row-template');
    var suggestUrl = @json(route('olt.suggest'));

    // Keep names as olts[0][…], olts[1][…] … and the numbering in order.
    function renumber() {
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function (tr, i) {
            tr.querySelector('.row-no').textContent = i + 1;
            tr.querySelectorAll('input').forEach(function (input) {
                var field = input.dataset.field || input.name.replace(/^olts\[\d+\]\[(\w+)\]$/, '$1');
                input.dataset.field = field;
                input.name = 'olts[' + i + '][' + field + ']';
            });
        });
        document.getElementById('row-count').textContent = rows.length;
        document.getElementById('save-label').textContent = rows.length > 1 ? 'Save ' + rows.length + ' OLTs' : 'Save OLT';
    }

    function addRow() {
        tbody.appendChild(tpl.content.firstElementChild.cloneNode(true));
        renumber();
    }

    document.getElementById('btn-add').addEventListener('click', addRow);
    document.getElementById('btn-add5').addEventListener('click', function () { for (var i = 0; i < 5; i++) addRow(); });

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-remove');
        if (!btn) return;
        if (tbody.querySelectorAll('tr').length > 1) {
            btn.closest('tr').remove();
        } else {
            btn.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = ''; });
        }
        renumber();
    });

    document.getElementById('btn-suggest').addEventListener('click', function () {
        var zone = document.getElementById('zone').value;
        var note = document.getElementById('suggest-note');
        if (!zone) { note.innerHTML = '<span class="text-danger">Choose a zone first.</span>'; return; }

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var btn = this;
        btn.disabled = true;

        var url = suggestUrl + '?zone=' + encodeURIComponent(zone) + '&count=' + rows.length
            + '&vlans=' + encodeURIComponent(document.getElementById('vlan-size').value || 4);

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (data) {
                rows.forEach(function (tr, i) {
                    var s = data.rows[i] || {};
                    [['name', '.f-name'], ['ip', '.f-ip'], ['vlan', '.f-vlan']].forEach(function (f) {
                        var input = tr.querySelector(f[1]);
                        if (!input.value && s[f[0]]) input.value = s[f[0]];
                    });
                });
                note.textContent = 'Filled empty fields — ' + data.note + '.';
            })
            .catch(function () { note.innerHTML = '<span class="text-danger">Could not get suggestions — try again.</span>'; })
            .finally(function () { btn.disabled = false; });
    });

    renumber();
});
</script>

@stop
