@extends('adminlte::page')

@section('title', 'Salary Sheet')

@php
    $bnMonth = \App\Support\Bangla::month($month);
    $posted = (bool) $sheet?->isPosted();
    $printUrl = route('accounts.salaries.index', ['month' => $month->format('Y-m'), 'print' => 1]);
@endphp

@section('content_header')
<x-accounts.header title="বেতন শিট" icon="fas fa-money-check-alt"
    subtitle="{{ $bnMonth }} {{ $month->year }} — Salary, House Rent, Bonus, Advance, Deduction, Net Pay">
    <a href="{{ $printUrl }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print"></i> Print</a>
</x-accounts.header>
@stop

@section('css')
<style>
    .sal-table th, .sal-table td { vertical-align: middle; white-space: nowrap; }
    .sal-table input { min-width: 5.5rem; font-size: .88rem; }
    .sal-table input.name { min-width: 11rem; }
    .sal-table input.money { text-align: right; font-variant-numeric: tabular-nums; }
    .sal-table td.net { font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
    .sal-table tfoot td { font-weight: 700; background: #1e3a8a; color: #fff; text-align: right; }
    .sal-status { padding: .55rem .9rem; border-radius: .6rem; font-size: .9rem; }
</style>
@stop

@section('content')

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<form method="GET" class="d-flex align-items-center flex-wrap mb-3" style="gap:.5rem">
    <a href="{{ route('accounts.salaries.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-sm btn-light"><i class="fas fa-chevron-left"></i></a>
    <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
    <a href="{{ route('accounts.salaries.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-sm btn-light"><i class="fas fa-chevron-right"></i></a>

    <span class="ml-auto sal-status {{ $sheet?->isPosted() ? 'bg-success text-white' : ($sheet ? 'bg-warning' : 'bg-light') }}">
        @if ($posted)
            <i class="fas fa-lock"></i> Posted to expenses (বেতন) on {{ $sheet->posted_at->format('d M Y') }} — locked
        @elseif ($sheet)
            <i class="fas fa-pen"></i> Saved, not posted to expenses yet
        @else
            <i class="fas fa-file"></i> New sheet — filled from {{ $rows->isNotEmpty() ? 'last month / employee salaries' : 'nothing yet' }}, not saved
        @endif
    </span>
</form>

<form method="POST" action="{{ route('accounts.salaries.save') }}" id="sal-form">
    @csrf
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <input type="hidden" name="post" id="sal-post" value="{{ $sheet?->isPosted() ? 1 : 0 }}">

    <fieldset @disabled($posted)>
    <div class="card acct-panel">
        <div class="card-header">
            <h3 class="card-title">
                {{ $bnMonth }} মাসের বেতন খরচের হিসাব {{ $month->year }}
                <small class="text-muted d-block">পরিশোধ: {{ \App\Support\Bangla::month($payMonth) }} {{ $payMonth->year }} — postpaid, the expense goes in {{ $payMonth->format('F') }}'s books</small>
            </h3>
            <div class="d-flex align-items-center" style="gap:.5rem">
                <label class="small mb-0 text-muted">Pay date</label>
                <input type="date" name="pay_date" value="{{ old('pay_date', \Illuminate\Support\Carbon::parse($payDate)->toDateString()) }}"
                       min="{{ $payMonth->copy()->startOfMonth()->toDateString() }}" class="form-control form-control-sm @error('pay_date') is-invalid @enderror" style="width:auto" required>
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm sal-table mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">SL</th>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th class="text-right">Salary</th>
                        <th class="text-right">House Rent</th>
                        <th class="text-right">Eid Bonus</th>
                        <th class="text-right">Advance</th>
                        <th class="text-right">Deduction</th>
                        <th class="text-right">Net Pay</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="sal-body">
                    @foreach (old('rows', $rows->map(fn ($r) => $r->only(['employee_id', 'emp_code', 'name', 'designation', 'salary', 'house_rent', 'bonus', 'advance', 'deduction']))->all()) as $i => $r)
                        <tr>
                            <td class="pl-3 text-muted js-sl"></td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][employee_id]" value="{{ $r['employee_id'] ?? '' }}" class="js-emp-id">
                                <input type="text" name="rows[{{ $i }}][emp_code]" value="{{ $r['emp_code'] ?? '' }}" class="form-control form-control-sm" style="min-width:5.5rem">
                            </td>
                            <td><input type="text" name="rows[{{ $i }}][name]" value="{{ $r['name'] ?? '' }}" class="form-control form-control-sm name" list="sal-employees" required></td>
                            <td><input type="text" name="rows[{{ $i }}][designation]" value="{{ $r['designation'] ?? '' }}" class="form-control form-control-sm"></td>
                            @foreach (['salary', 'house_rent', 'bonus', 'advance', 'deduction'] as $f)
                                <td><input type="number" step="0.01" min="0" name="rows[{{ $i }}][{{ $f }}]" value="{{ (float) ($r[$f] ?? 0) ?: '' }}" class="form-control form-control-sm money js-{{ $f }}" placeholder="0"></td>
                            @endforeach
                            <td class="net js-net">0</td>
                            <td class="pr-2">@unless ($posted)<button type="button" class="btn btn-link btn-sm text-danger p-0 js-remove" title="Remove row"><i class="fas fa-times"></i></button>@endunless</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-left pl-3">Total</td>
                        <td id="t-salary">0</td><td id="t-house_rent">0</td><td id="t-bonus">0</td><td id="t-advance">0</td><td id="t-deduction">0</td>
                        <td id="t-net">0</td><td></td>
                    </tr>
                </tfoot>
            </table>
            <datalist id="sal-employees">
                @foreach ($employees as $e)<option value="{{ $e->name }}">@endforeach
            </datalist>
        </div>
        <div class="card-footer d-flex flex-wrap align-items-center" style="gap:.5rem">
            @if ($posted)
                <span class="small text-muted"><i class="fas fa-lock"></i> Posted and locked. To change it, use <strong>Undo posting</strong> below, fix it, then post again.</span>
            @else
                <button type="button" class="btn btn-light btn-sm" id="sal-add"><i class="fas fa-plus"></i> Add row</button>
                <span class="small text-muted">Net Pay = Salary + House Rent + Bonus − Advance − Deduction. Typing a known employee's name fills their ID and designation.</span>
            @endif
            <div class="ml-auto d-flex" style="gap:.5rem">
                <a href="{{ $printUrl }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print"></i> Print Salary Sheet</a>
                @unless ($posted)
                    <button type="submit" class="btn btn-outline-primary" data-post="0"><i class="fas fa-save"></i> Save</button>
                    <button type="submit" class="btn btn-success" data-post="1" id="sal-post-btn"><i class="fas fa-check"></i> Save &amp; Post (once a month)</button>
                @endunless
            </div>
        </div>
    </div>
    </fieldset>
</form>

@if ($posted)
    <form method="POST" action="{{ route('accounts.salaries.unpost') }}" class="js-confirm-delete mb-4"
          data-confirm-message="Remove this month's বেতন expenses? The sheet itself is kept.">
        @csrf
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <button class="btn btn-link btn-sm text-danger p-0"><i class="fas fa-undo"></i> Undo posting to expenses</button>
    </form>
@endif

<template id="sal-row">
    <tr>
        <td class="pl-3 text-muted js-sl"></td>
        <td><input type="hidden" name="rows[__i__][employee_id]" class="js-emp-id"><input type="text" name="rows[__i__][emp_code]" class="form-control form-control-sm" style="min-width:5.5rem"></td>
        <td><input type="text" name="rows[__i__][name]" class="form-control form-control-sm name" list="sal-employees" required></td>
        <td><input type="text" name="rows[__i__][designation]" class="form-control form-control-sm"></td>
        <td><input type="number" step="0.01" min="0" name="rows[__i__][salary]" class="form-control form-control-sm money js-salary" placeholder="0"></td>
        <td><input type="number" step="0.01" min="0" name="rows[__i__][house_rent]" class="form-control form-control-sm money js-house_rent" placeholder="0"></td>
        <td><input type="number" step="0.01" min="0" name="rows[__i__][bonus]" class="form-control form-control-sm money js-bonus" placeholder="0"></td>
        <td><input type="number" step="0.01" min="0" name="rows[__i__][advance]" class="form-control form-control-sm money js-advance" placeholder="0"></td>
        <td><input type="number" step="0.01" min="0" name="rows[__i__][deduction]" class="form-control form-control-sm money js-deduction" placeholder="0"></td>
        <td class="net js-net">0</td>
        <td class="pr-2"><button type="button" class="btn btn-link btn-sm text-danger p-0 js-remove" title="Remove row"><i class="fas fa-times"></i></button></td>
    </tr>
</template>
@stop

@section('js')
<script>
(function () {
    var employees = @json($employees->keyBy('name'));
    var body = document.getElementById('sal-body');
    var fields = ['salary', 'house_rent', 'bonus', 'advance', 'deduction'];
    var next = body.children.length;
    var fmt = function (v) { return Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 }); };

    function val(row, f) { return parseFloat(row.querySelector('.js-' + f).value) || 0; }

    function recalc() {
        var totals = { salary: 0, house_rent: 0, bonus: 0, advance: 0, deduction: 0, net: 0 };
        Array.prototype.forEach.call(body.children, function (row, i) {
            row.querySelector('.js-sl').textContent = i + 1;
            var net = val(row, 'salary') + val(row, 'house_rent') + val(row, 'bonus') - val(row, 'advance') - val(row, 'deduction');
            row.querySelector('.js-net').textContent = fmt(net);
            fields.forEach(function (f) { totals[f] += val(row, f); });
            totals.net += net;
        });
        Object.keys(totals).forEach(function (k) { document.getElementById('t-' + k).textContent = fmt(totals[k]); });
    }

    body.addEventListener('input', function (e) {
        // Picking a known employee fills ID, designation and default pay.
        if (e.target.classList.contains('name') && employees[e.target.value]) {
            var emp = employees[e.target.value], row = e.target.closest('tr');
            row.querySelector('.js-emp-id').value = emp.id;
            var code = row.querySelector('input[name$="[emp_code]"]'), des = row.querySelector('input[name$="[designation]"]');
            if (!code.value) code.value = emp.emp_code || '';
            if (!des.value) des.value = emp.designation || '';
            if (!row.querySelector('.js-salary').value && emp.basic_salary) row.querySelector('.js-salary').value = parseFloat(emp.basic_salary);
            if (!row.querySelector('.js-house_rent').value && emp.house_rent) row.querySelector('.js-house_rent').value = parseFloat(emp.house_rent);
        }
        recalc();
    });

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-remove');
        if (btn) { btn.closest('tr').remove(); recalc(); }
    });

    var addBtn = document.getElementById('sal-add');
    if (addBtn) addBtn.addEventListener('click', function () {
        var html = document.getElementById('sal-row').innerHTML.replace(/__i__/g, next++);
        body.insertAdjacentHTML('beforeend', html);
        recalc();
        body.lastElementChild.querySelector('.name').focus();
    });

    document.querySelectorAll('#sal-form [data-post]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            if (b.dataset.post === '1' && !window.confirm('Post this month\'s salary? It can be posted only once and will be locked afterwards.')) {
                e.preventDefault();
                return;
            }
            document.getElementById('sal-post').value = b.dataset.post;
        });
    });

    recalc();
})();
</script>
@stop
